<?php

namespace App\Console\Commands;

use App\Models\Pengaturan;
use App\Models\SaldoKoin;
use App\Models\Transaksi;
use App\Models\TransaksiSaldoKoin;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Services\Firebases;
use Illuminate\Support\Facades\Log;

class AutoCompleteSiapDiambil extends Command
{
    protected $signature = 'transactions:auto-complete-siap-diambil';
    protected $description = 'Otomatis mengubah status siap_diambil menjadi selesai jika sudah lebih dari 1 jam';

    public function handle(Firebases $firebases)
    {
        $count = 0;

        $now = Carbon::now('Asia/Jakarta');

        $thresholdMinutes = Pengaturan::where('nama', 'auto_complete_siap_diambil')
            ->value('nilai') ?? 60;

        $thresholdMinutes = (int) $thresholdMinutes;

        // Ambil transaksi yang sudah lewat X menit setelah siap_diambil
        $transaksiList = Transaksi::where('status', 'siap_diambil')
            ->where('updated_at', '<=', $now->copy()->subMinutes($thresholdMinutes))
            ->get();

        foreach ($transaksiList as $transaksi) {

            // ==============================
            // ⚡ HANDLE MULTITENANT
            // ==============================
            if ($transaksi->multitenant_id) {

                $related = Transaksi::where('multitenant_id', $transaksi->multitenant_id)->get();

                if ($related->count() == 2) {

                    $t1 = $related[0];
                    $t2 = $related[1];

                    // CASE 1: Keduanya siap_diambil → selesai keduanya
                    if ($t1->status === 'siap_diambil' && $t2->status === 'siap_diambil') {

                        foreach ($related as $t) {
                            $this->completeTransaction($t);
                            $this->processCashback($t, $firebases);
                            $count++;
                        }

                        Log::info("Multitenant {$transaksi->multitenant_id}: kedua transaksi selesai (auto 1 jam).");
                        continue;
                    }

                    // CASE 2: Salah satu refund_selesai
                    elseif (
                        ($t1->status === 'refund_selesai' && $t2->status === 'siap_diambil') ||
                        ($t2->status === 'refund_selesai' && $t1->status === 'siap_diambil')
                    ) {

                        $siap = $t1->status === 'siap_diambil' ? $t1 : $t2;
                        $refund = $t1->status === 'refund_selesai' ? $t1 : $t2;

                        // Kembalikan dana refund + cashback
                        $this->refundBalance($refund, $firebases);

                        // Selesaikan yang siap_diambil
                        $this->completeTransaction($siap);
                        $this->processCashback($siap, $firebases);

                        Log::info("Multitenant {$transaksi->multitenant_id}: ada refund → pengembalian & selesai 1 transaksi.");
                        $count++;
                        continue;
                    }

                    // CASE 3:
                    // satu siap_diambil + satunya pesanan_diproses → hanya selesaikan yang siap_diambil
                    elseif (
                        ($t1->status === 'siap_diambil' && $t2->status === 'pesanan_diproses') ||
                        ($t2->status === 'siap_diambil' && $t1->status === 'pesanan_diproses')
                    ) {

                        $siap = $t1->status === 'siap_diambil' ? $t1 : $t2;

                        $this->completeTransaction($siap);
                        $this->processCashback($siap, $firebases);

                        Log::info("Multitenant {$transaksi->multitenant_id}: hanya 1 selesai (pasangan masih diproses)");
                        $count++;
                        continue;
                    }
                }
            }

            // ==============================
            // ⚡ SINGLE TRANSAKSI NORMAL
            // ==============================
            $this->completeTransaction($transaksi);
            $this->processCashback($transaksi, $firebases);

            $count++;
        }

        $this->info("$count transaksi berhasil diupdate menjadi selesai.");
        Log::info("$count transaksi selesai otomatis (>1 jam) pada " . Carbon::now('Asia/Jakarta')->toDateTimeString());

        return Command::SUCCESS;
    }

    // ==============================
    // 🔧 HELPER: SET TRANSAKSI SELESAI
    // ==============================
    private function completeTransaction($transaksi)
    {
        $transaksi->status = 'selesai';
        $transaksi->updated_at = Carbon::now('Asia/Jakarta');
        $transaksi->save();

        if ($transaksi->metode_pembayaran != 'transfer') {
            $transaksi->listTransaksiDetail()->update(['status' => 'selesai']);
        }
    }

    // ==============================
    // 🔧 HELPER: PROSES CASHBACK
    // ==============================
    private function processCashback($transaksi, $firebases)
    {
        if ($transaksi->cashback_amount <= 0) return;

        $user = $transaksi->user;

        $saldo = SaldoKoin::firstOrCreate(
            ['user_id' => $user->id],
            ['jumlah' => 0]
        );

        $saldo->jumlah += $transaksi->cashback_amount;
        $saldo->save();

        TransaksiSaldoKoin::create([
            'user_id'   => $user->id,
            'jumlah'    => $transaksi->cashback_amount,
            'tipe'      => 'masuk',
            'deskripsi' => "Cashback pesanan {$transaksi->kode_pemesanan} telah masuk",
        ]);

        // Kirim notifikasi
        $fcmUser = User::with('fcmTokens')->find($user->id);
        $tokens = $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() ?? [];

        if (!empty($tokens)) {
            $title = 'Cashback berhasil didapatkan';
            $body  = "Cashback sebanyak {$transaksi->cashback_amount} berhasil masuk ke akunmu.";

            $firebases->withNotification($title, $body)
                ->withData([
                    'title' => $title,
                    'body' => $body,
                    'type' => 'cashback',
                    'transaksi_id' => $transaksi->id,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ])
                ->sendToFallback($tokens);
        }
    }

    // ==============================
    // 🔧 HELPER: KEMBALIKAN DANA REFUND + CASHBACK
    // ==============================
    private function refundBalance($transaksi, $firebases)
    {
        $user = $transaksi->user;

        $saldo = SaldoKoin::firstOrCreate(
            ['user_id' => $user->id],
            ['jumlah' => 0]
        );

        // Kembalikan total amount
        $saldo->jumlah += $transaksi->total;
        $saldo->save();

        TransaksiSaldoKoin::create([
            'user_id'   => $user->id,
            'jumlah'    => $transaksi->total,
            'tipe'      => 'masuk',
            'deskripsi' => "Pengembalian dana refund multitenant pesanan {$transaksi->kode_pemesanan}",
        ]);

        // Jika ada cashback_amount pada transaksi refund, kembalikan juga cashback-nya
        if ($transaksi->cashback_amount > 0) {
            $saldo->jumlah += $transaksi->cashback_amount;
            $saldo->save();

            TransaksiSaldoKoin::create([
                'user_id'   => $user->id,
                'jumlah'    => $transaksi->cashback_amount,
                'tipe'      => 'masuk',
                'deskripsi' => "Pengembalian cashback refund multitenant pesanan {$transaksi->kode_pemesanan}",
            ]);

            // Kirim notifikasi untuk pengembalian cashback
            $fcmUser = User::with('fcmTokens')->find($user->id);
            $tokens = $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() ?? [];

            if (!empty($tokens)) {
                $title = 'Cashback dikembalikan';
                $body  = "Cashback sebanyak {$transaksi->cashback_amount} telah dikembalikan ke akunmu karena refund.";

                $firebases->withNotification($title, $body)
                    ->withData([
                        'title' => $title,
                        'body' => $body,
                        'type' => 'cashback_refund',
                        'transaksi_id' => $transaksi->id,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ])
                    ->sendToFallback($tokens);
            }
        }

        // Notifikasi untuk pengembalian dana utama
        $fcmUser = User::with('fcmTokens')->find($user->id);
        $tokens = $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() ?? [];

        if (!empty($tokens)) {
            $title = 'Dana refund dikembalikan';
            $body  = "Dana sebanyak {$transaksi->total} telah dikembalikan ke saldomu karena pembatalan pesanan.";

            $firebases->withNotification($title, $body)
                ->withData([
                    'title' => $title,
                    'body' => $body,
                    'type' => 'refund',
                    'transaksi_id' => $transaksi->id,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ])
                ->sendToFallback($tokens);
        }
    }
}
