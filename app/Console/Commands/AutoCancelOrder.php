<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Pengaturan;
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
            ->where('created_at', '<=', $threshold)
            ->get();

        foreach ($transaksis as $transaksi) {
            DB::beginTransaction();
            try {
                $user = $transaksi->user;
                $transaksi->status = 'pesanan_ditolak';
                $transaksi->save();

                if ($user && $user->fcm_token) {
                    $firebases->withNotification('Pesanan Dibatalkan','Pesanan #' . $transaksi->id . ' tidak direspond tenant.')->sendMessages($user->fcm_token);
                }

                $this->refundKoin($transaksi);

                $transaksi->status = 'refund_selesai'; 
                $transaksi->save();

                DB::commit();

                if ($user && $user->fcm_token) {
                    $firebases->withNotification('Refund Berhasil','Koin dari pesanan #' . $transaksi->id . ' telah berhasil dikembalikan ke akun kamu.')->sendMessages($user->fcm_token);
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
    }
}