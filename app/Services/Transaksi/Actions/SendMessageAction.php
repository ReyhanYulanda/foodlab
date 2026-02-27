<?php

namespace App\Services\Transaksi\Actions;

use App\Models\Message;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\Firebases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SendMessageAction
{
    public function execute($transaksiId, Request $request)
    {
        try {
            $transaksi = Transaksi::findOrFail($transaksiId);
            $userRole = Auth::user()->roles->pluck('name')->first();
            $request->validate([
                'message' => 'required|string'
            ]);

            $message = Message::create([
                'transaksi_id' => $transaksiId,
                'sender_id' => Auth::id(),
                'text' => $request->message,
            ]);

            $firebase = new Firebases();

            if ($userRole == 'tenant') {
                $pembeli = User::with('fcmTokens')->find($transaksi->user_id);
                $tokens = $pembeli ? $pembeli->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];
                if (!empty($tokens)) {
                    $firebase->withNotification('Pesan Baru dari Tenant', $message->text)
                        ->withData([
                            'title' => 'Pesan Baru dari Tenant',
                            'body' => $message->text,
                            'transaksi_id' => $transaksiId,
                            'type' => 'chat'
                        ])->sendToFallback($tokens);
                }
            } elseif ($userRole == 'kurir_masbro') {
                $pembeli = User::with('fcmTokens')->find($transaksi->user_id);
                $tokens = $pembeli ? $pembeli->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];
                if (!empty($tokens)) {
                    $firebase->withNotification('Pesan Baru dari Driver', $message->text)
                        ->withData([
                            'title' => 'Pesan Baru dari Driver',
                            'body' => $message->text,
                            'transaksi_id' => $transaksiId,
                            'type' => 'chat'
                        ])->sendToFallback($tokens);
                }
            } else {
                $tenant = User::with('fcmTokens')->find($transaksi->tenant_id);
                $tenantTokens = $tenant ? $tenant->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];
                if (!empty($tenantTokens)) {
                    $firebase->withNotification('Pesan Baru dari Pembeli', $message->text)
                        ->withData([
                            'title' => 'Pesan Baru dari Pembeli',
                            'body' => $message->text,
                            'transaksi_id' => $transaksiId,
                            'type' => 'chat'
                        ])->sendToTenant($tenantTokens);
                }

                if ($transaksi->driver_id) {
                    $driver = User::with('fcmTokens')->find($transaksi->driver_id);
                    $driverTokens = $driver ? $driver->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];
                    if (!empty($driverTokens)) {
                        $firebase->withNotification('Pesan Baru dari Pembeli', $message->text)
                            ->withData([
                                'title' => 'Pesan Baru dari Pembeli',
                                'body' => $message->text,
                                'transaksi_id' => $transaksiId,
                                'type' => 'chat'
                            ])->sendToDriver($driverTokens);
                    }
                }
            }

            return response()->json([
                'message' => 'Pesan berhasil dikirim',
                'data' => $message
            ]);
        } catch (\Throwable $e) {
            Log::error("SendMessage Error: " . $e->getMessage());
            return response()->json([
                'message' => 'Gagal mengirim pesan',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
