<?php

namespace App\Console\Commands;

use App\Http\Controllers\Transaksi\TransaksiController;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\Firebases;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendSiapDiantarNotifications extends Command
{
    protected $signature = 'notifikasi:siap-diantar';
    protected $description = 'Kirim notifikasi setiap 1 menit jika ada pesanan siap diantar';

    public function handle(Firebases $firebases)
    {
        $transaksi = Transaksi::whereNull('driver_id')
            ->where('isAntar', 1)
            ->whereNotIn('status', ['refund_selesai', 'gagal_bayar', 'pending'])
            ->get();

        if (!$transaksi) {
            $this->info('Tidak ada pesanan siap diantar.');
            return Command::SUCCESS;
        }

        // Ambil semua token driver yang online
        $tokens = User::role('masbro')
            ->where('isOnline', 1)
            ->with('fcmTokens')
            ->get()
            ->flatMap(fn($user) => $user->fcmTokens->pluck('fcm_token'))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($tokens)) {
            $this->info('Tidak ada driver online.');
            return Command::SUCCESS;
        }

        /** ------------------------------------------------------------------
         *  1. HANDLE PRIORITAS
         * ------------------------------------------------------------------*/
        $prioritas = $transaksi->where('isPriority', 1)
            ->whereNull('driver_id')
            ->first();
        if ($prioritas) {
            $this->kirimNotif($firebases, $tokens);
            $this->info('Notifikasi PRIORITAS terkirim.');
            return Command::SUCCESS;
        }

        /** ------------------------------------------------------------------
         *  2. HANDLE MULTITENANT
         * ------------------------------------------------------------------*/
        $isMultiTenant = !empty($transaksi->multitenant_id);

        if ($isMultiTenant) {
            $groupTransaksi = Transaksi::where('multitenant_id', $transaksi->multitenant_id)->get();

            // cek apakah MASIH ADA pesanan_masuk selain transaksi ini
            $stillHasPending = $groupTransaksi
                ->where('status', 'pesanan_masuk')
                ->where('id', '!=', $transaksi->id)
                ->where('isPriority', 0)
                ->isNotEmpty();

            // RULE:
            // siap_diantar + masih ada pesanan_masuk → TIDAK KIRIM
            if ($stillHasPending) {
                $this->info('Masih ada pesanan masuk lain. Notif dibatalkan.');
                return Command::SUCCESS;
            }
        }

        /** ------------------------------------------------------------------
         *  3. NON MULTITENANT → selalu kirim notif
         * ------------------------------------------------------------------*/
        $siapDiantar = $transaksi->firstWhere('status', 'siap_diantar');

        if ($siapDiantar) {
            $this->kirimNotif($firebases, $tokens);

            $this->info('Notifikasi terkirim ke driver.');
            Log::info('Notifikasi siap diantar terkirim pada ' . now('Asia/Jakarta'));
        }

        return Command::SUCCESS;
    }

    /** Helper function */
    private function kirimNotif(Firebases $firebases, array $tokens)
    {
        $firebases
            ->withNotification(
                'Ada Pesanan Siap Diantar',
                'Ada pesanan siap diantar! Yuk, ambil dan antar sekarang!'
            )
            ->withData([
                'title' => 'Ada Pesanan Siap Diantar',
                'body' => 'Ada pesanan siap diantar! Yuk, ambil dan antar sekarang!',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ])
            ->sendToDriver($tokens);
    }
}
