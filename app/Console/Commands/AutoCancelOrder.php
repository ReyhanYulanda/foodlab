<?php

namespace App\Console\Commands;

use App\Models\CatatVoucher;
use Illuminate\Console\Command;
use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Pengaturan;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Firebases;

class AutoCancelOrder extends Command
{
    protected $signature = 'order:autocancel';
    protected $description = 'Batalkan otomatis pesanan_masuk setelah waktu tertentu dari pengaturan';

    public function handle(Firebases $firebases)
    {
        $timeout = Pengaturan::where('nama', 'timeout_pesanan')->value('nilai');
        $timeout = $timeout ?? 10;

        $threshold = Carbon::now()->subMinutes($timeout);

        $transaksis = Transaksi::where('status', 'pesanan_masuk')
            ->where('updated_at', '<=', $threshold)
            ->get();

        foreach ($transaksis as $transaksi) {
            DB::beginTransaction();
            try {
                $user = $transaksi->user;
                $fcmUser = User::with('fcmTokens')->find($transaksi->user_id);
                $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];


                $transaksi->status = 'pesanan_ditolak';
                $transaksi->catatan_penolakan = 'Pesanan dibatalkan otomatis karena tidak direspons tenant dalam waktu ' . $timeout . ' menit.';
                $transaksi->save();

                if ($user && $user->fcm_token) {
                    $firebases
                        ->withNotification(
                            'Pesanan Dibatalkan',
                            'Pesanan #' . $transaksi->id . ' tidak direspons tenant.'
                        )
                        ->withData([
                            'title' => 'Pesanan Dibatalkan',
                            'body' => 'Pesanan #' . $transaksi->id . ' tidak direspond tenant.',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                        ])->sendToFallback($fcmUserToken);
                }

                $tenants = $transaksi->listTransaksiDetail()
                    ->with('menus.tenants.pemilik')
                    ->get()
                    ->pluck('menus.tenants')
                    ->flatten()
                    ->unique('id');

                foreach ($tenants as $tenant) {
                    if ($tenant && $tenant->pemilik) {
                        $pemilikUser = User::with('fcmTokens')->find($tenant->pemilik->id);

                        $fcmTenantTokens = $pemilikUser
                            ? $pemilikUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray()
                            : [];

                        if (!empty($fcmTenantTokens)) {
                            $firebases
                                ->withNotification(
                                    'Pesanan Dibatalkan Otomatis',
                                    'Pesanan #' . $transaksi->id . ' dibatalkan karena tidak direspons tepat waktu.'
                                )
                                ->withData([
                                    'title' => 'Pesanan Dibatalkan Otomatis',
                                    'body' => 'Pesanan #' . $transaksi->id . ' dibatalkan karena tidak direspons tepat waktu.',
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                                ])
                                ->sendToFallback($fcmTenantTokens); // sekarang bisa array
                        }
                    }
                }

                $this->refundKoin($transaksi);

                $transaksi->status = 'refund_selesai';
                $transaksi->save();

                DB::commit();

                if ($user && $user->fcm_token) {
                    $firebases
                        ->withNotification(
                            'Refund Berhasil',
                            'Koin dari pesanan #' . $transaksi->id . ' telah berhasil dikembalikan ke akun kamu.'
                        )
                        ->withData([
                            'title' => 'Refund Berhasil',
                            'body' => 'Koin dari pesanan #' . $transaksi->id . ' telah berhasil dikembalikan ke akun kamu.',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                        ])->sendToFallback($fcmUserToken);
                }

                Log::info("Transaksi #{$transaksi->id} dibatalkan otomatis setelah $timeout menit dan refund berhasil.");
            } catch (\Throwable $e) {
                DB::rollback();
                Log::error("Gagal membatalkan transaksi #{$transaksi->id}: " . $e->getMessage());
            }
        }

        $this->info("Auto cancel executed with timeout $timeout minutes.");
    }

    private function refundKoin(Transaksi $transaksi)
    {
        if ($transaksi->status === 'refund_selesai') {
            throw new \Exception("Transaksi sudah direfund sebelumnya.");
        }

        $saldo = \App\Models\SaldoKoin::firstOrCreate(['user_id' => $transaksi->user_id]);
        $saldo->jumlah += $transaksi->total;
        $saldo->save();

        \App\Models\TransaksiSaldoKoin::create([
            'user_id' => $transaksi->user_id,
            'jumlah' => $transaksi->total,
            'tipe' => 'masuk',
            'deskripsi' => 'Refund pesanan #' . $transaksi->id,
        ]);

        CatatVoucher::where('transaksi_id', $transaksi->id)->delete();

        if ($transaksi->cashback_amount > 0 && $transaksi->voucher_id) {
            $voucher = $transaksi->voucher;

            if ($voucher) {
                $voucher->increment('quantity');

                if ($voucher->cashback) {
                    $voucher->cashback->increment('quantity');
                }

                Log::info("Voucher #{$voucher->id} dikembalikan karena refund transaksi #{$transaksi->id}");
            }
        }
    }
}
