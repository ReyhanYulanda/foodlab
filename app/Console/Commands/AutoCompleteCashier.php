<?php

namespace App\Console\Commands;

use App\Models\Cashier;
use App\Models\Pengaturan;
use App\Models\SaldoKoin;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Services\Firebases;
use Illuminate\Support\Facades\Log;

class AutoCompleteCashier extends Command
{
    protected $signature = 'kasir:auto-complete-pesanan-diproses';
    protected $description = 'Otomatis mengubah status pesanan_diproses kasir menjadi selesai jika sudah lebih dari 1 jam';

    public function handle(Firebases $firebases)
    {
        $count = 0;

        $cashierList = Cashier::where('status', 'pesanan_diproses')->get();

        foreach ($cashierList as $cashier) {
            $cashier->status = 'selesai';
            $cashier->updated_at = Carbon::now('Asia/Jakarta');
            $cashier->save();
            $user = $cashier->user;

            // Kirim notifikasi FCM
            $fcmUser = User::with('fcmTokens')->find($user->id);
            $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];

            if (!empty($fcmUserToken)) {
                $title = 'Kasir berhasil di selesaikan sistem';
                $body = "Kasir pesanan {$cashier->kode_pemesanan} telah di selesaikan sistem.";

                $firebases->withNotification($title, $body)
                    ->withData([
                        'title' => $title,
                        'body' => $body,
                        'type' => 'cashier',
                        'cashier_id' => $cashier->id,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ])
                    ->sendToFallback($fcmUserToken);
            }
            $count++;
        }

        $this->info("$count kasir berhasil diupdate menjadi selesai.");
        Log::info("$count kasir berhasil diupdate menjadi selesai pada " . Carbon::now('Asia/Jakarta')->toDateTimeString());
        return Command::SUCCESS;
    }
}
