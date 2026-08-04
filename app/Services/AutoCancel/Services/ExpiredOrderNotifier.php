<?php

namespace App\Services\AutoCancel\Services;

use App\Models\Transaksi;
use App\Models\User;
use App\Services\Firebases;
use Illuminate\Support\Facades\Log;

class ExpiredOrderNotifier
{
    public function __construct(
        private Firebases $firebases
    ) {}

    public function getUserFcmTokens(User $user): array
    {
        $fcmUser = User::with('fcmTokens')->find($user->id);

        return $fcmUser
            ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray()
            : [];
    }

    public function notifySingleRefund(Transaksi $transaksi, array $fcmTokens): void
    {
        if (empty($fcmTokens)) {
            return;
        }

        $this->firebases
            ->withNotification('Refund Berhasil', 'Koin dari pesanan #' . $transaksi->id . ' telah dikembalikan ke akun kamu.')
            ->withData([
                'title' => 'Refund Berhasil',
                'body' => 'Koin dari pesanan #' . $transaksi->id . ' telah dikembalikan ke akun kamu.',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
            ])
            ->sendToFallback($fcmTokens);
    }

    public function notifyMultitenantCancellation(Transaksi $transaksi, array $fcmTokens): void
    {
        if (empty($fcmTokens)) {
            return;
        }

        $this->firebases
            ->withNotification(
                'Pesanan Dibatalkan Otomatis',
                'Salah satu pesanan multitenant (#' . $transaksi->id . ') dibatalkan otomatis karena tenant tidak merespons.'
            )
            ->withData([
                'title' => 'Pesanan Dibatalkan Otomatis',
                'body' => 'Salah satu pesanan dalam grup multitenant dibatalkan otomatis karena tenant tidak merespons.',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
            ])
            ->sendToFallback($fcmTokens);
    }

    public function notifyTenantCancellation(Transaksi $transaksi): void
    {
        $tenant = optional($transaksi->listTransaksiDetail()->with('menus.tenants.pemilik')->first())->menus->tenants ?? null;

        if (!$tenant || !$tenant->pemilik) {
            return;
        }

        $pemilikUser = User::with('fcmTokens')->find($tenant->pemilik->id);
        $tokens = $pemilikUser
            ? $pemilikUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray()
            : [];

        if (empty($tokens)) {
            return;
        }

        $this->firebases
            ->withNotification(
                'Pesanan Dibatalkan Otomatis',
                'Pesanan multitenant #' . $transaksi->multitenant_id . ' dibatalkan karena tenant tidak merespons.'
            )
            ->withData([
                'title' => 'Pesanan Dibatalkan Otomatis',
                'body' => 'Pesanan multitenant #' . $transaksi->multitenant_id . ' dibatalkan otomatis oleh sistem.',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
            ])
            ->sendToFallback($tokens);
    }

    public function notifyFullMultitenantRefund(Transaksi $transaksi, array $fcmTokens): void
    {
        if (empty($fcmTokens)) {
            return;
        }

        $this->firebases
            ->withNotification(
                'Refund Multitenant Berhasil',
                'Koin dari pesanan multitenant #' . $transaksi->multitenant_id . ' telah dikembalikan penuh ke akun kamu.'
            )
            ->withData([
                'title' => 'Refund Multitenant Berhasil',
                'body' => 'Koin dari pesanan multitenant #' . $transaksi->multitenant_id . ' telah dikembalikan penuh ke akun kamu.',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
            ])
            ->sendToFallback($fcmTokens);
    }

    public function notifyPriorityCancelToDrivers(Transaksi $transaksi): void
    {
        if ($transaksi->driver_id == null) {
            $masbroTokens = User::role('masbro')
                ->with('fcmTokens')
                ->get()
                ->flatMap(fn($user) => $user->fcmTokens->pluck('fcm_token'))
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            if (!empty($masbroTokens)) {
                $this->firebases
                    ->withNotification('Pesanan prioritas', "Salah satu pesanan prioritas  dibatalkan #{$transaksi->id}")
                    ->withData([
                        'title' => 'Pesanan Prioritas',
                        'body' => "Salah satu pesanan prioritas  dibatalkan #{$transaksi->id}",
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ])->sendToFallback($masbroTokens);

                Log::info('Sending FCM to driver', ['tokens' => $masbroTokens]);
            }
        } else {
            $masbroTokens = User::role('masbro')
                ->where('isOnline', 1)
                ->with('fcmTokens')
                ->get()
                ->flatMap(fn($user) => $user->fcmTokens->pluck('fcm_token'))
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            if (!empty($masbroTokens)) {
                $this->firebases
                    ->withNotification('Pesanan prioritas', "Salah satu pesanan prioritas  dibatalkan #{$transaksi->id}")
                    ->withData([
                        'title' => 'Pesanan Prioritas',
                        'body' => "Salah satu pesanan prioritas  dibatalkan #{$transaksi->id}",
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ])->sendToDriver($masbroTokens);

                Log::info('Sending FCM to driver', ['tokens' => $masbroTokens]);
            }
        }
    }
}
