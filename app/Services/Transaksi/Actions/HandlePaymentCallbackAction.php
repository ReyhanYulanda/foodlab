<?php

namespace App\Services\Transaksi\Actions;

use App\Models\Checkout;
use App\Models\Cashier;
use App\Models\SaldoKoin;
use App\Models\TopUp;
use App\Models\Transaksi;
use App\Models\TransaksiSaldoKoin;
use App\Models\User;
use App\Services\Firebases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandlePaymentCallbackAction
{
    public function execute(Request $request)
    {
        $serverKey = config('custom.midtrans_server_key');
        $json = $request->all();

        Log::info('Webhook Callback dari Midtrans:', $json);

        $orderId = $json['order_id'] ?? null;
        $statusCode = $json['status_code'] ?? null;
        $grossAmount = $json['gross_amount'] ?? null;
        $signatureKey = $json['signature_key'] ?? null;

        if (!$orderId || !$statusCode || !$grossAmount || !$signatureKey) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $mySignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
        if ($signatureKey !== $mySignature) {
            Log::warning('Invalid signature key dari Midtrans', $json);
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $transactionStatus = $json['transaction_status'] ?? 'unknown';

        $checkout = Checkout::where('midtrans_request_id', $orderId)->first();

        if ($checkout) {
            DB::transaction(function () use ($checkout, $transactionStatus, $json) {
                if (in_array($transactionStatus, ['capture', 'settlement'])) {
                    $checkout->update([
                        'status_bayar' => 'settlement',
                        'tgl_bayar' => $json['settlement_time'] ?? now()
                    ]);
                    Log::info("Checkout ID {$checkout->id} sudah dibayar. Status checkout ganti ke settlement.");

                    $transaksi = Transaksi::find($checkout->transaksi_id);
                    if ($transaksi && $transaksi->status === 'pending') {
                        $transaksi->status = 'pesanan_masuk';
                        $transaksi->save();

                        TransaksiSaldoKoin::create([
                            'user_id' => $transaksi->user_id,
                            'jumlah' => -$transaksi->total,
                            'tipe' => 'keluar',
                            'deskripsi' => 'Pembayaran pesanan (QRIS) #' . $transaksi->id,
                        ]);

                        if ($transaksi->multitenant_id) {

                            $relatedTransaksi = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
                                ->where('id', '!=', $transaksi->id)
                                ->get();

                            foreach ($relatedTransaksi as $t) {
                                if ($t->status === 'pending') {
                                    $t->status = 'pesanan_masuk';
                                    $t->save();
                                }

                                $tenantUser = User::with('fcmTokens')->find($t->tenant_id);
                                $tenantTokens = $tenantUser ? $tenantUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];

                                if (!empty($tenantTokens)) {
                                    $firebases = new Firebases();
                                    $firebases
                                        ->withNotification('Pesanan Masuk', 'Ada pesanan baru, segera proses!')
                                        ->withData([
                                            'title' => 'Pesanan Masuk',
                                            'body' => 'Ada pesanan baru multitenant, silakan cek detailnya.',
                                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                        ])->sendToTenant($tenantTokens);
                                }
                            }
                        }

                        if ($transaksi->multitenant_id == null) { {
                                $tenantUser = User::with('fcmTokens')->find($transaksi->tenant_id);
                                $fcmTenantToken = $tenantUser ? $tenantUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];
                                if (!empty($fcmTenantToken)) {
                                    $firebases = new Firebases();
                                    $firebases
                                        ->withNotification('Pesanan Masuk', 'Ada pesanan baru, segera proses!')
                                        ->withData([
                                            'title' => 'Pesanan Masuk',
                                            'body' => 'Ada pesanan baru yang masuk! Silakan cek aplikasi untuk detailnya.',
                                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                        ])->sendToTenant($fcmTenantToken);
                                }
                            }
                        }

                        $user = User::with('fcmTokens')->find($transaksi->user_id);
                        $fcmUserToken = $user ? $user->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];
                        if (!empty($fcmUserToken)) {
                            $firebases = new Firebases();
                            $firebases
                                ->withNotification('Pembayaran Pesanan Berhasil', 'Pesanan ' . $transaksi->id . ' telah masuk ke tenant!')
                                ->withData([
                                    'title' => 'Pembayaran Pesanan Berhasil',
                                    'body' => 'Pesanan ' . $transaksi->id . ' telah masuk ke tenant!',
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                ])->sendToFallback($fcmUserToken);
                        }
                    }
                    $cashier = Cashier::find($checkout->cashier_id);
                    if ($cashier && $cashier->status === 'pending') {
                        $cashier->status = 'pesanan_diproses';
                        $cashier->save();
                        Log::info("Cashier ID {$cashier->id} sudah dibayar. Status cashier ganti ke pesanan_diproses.");

                        $tenantUser = User::with('fcmTokens')->find($cashier->user_id);
                        $fcmTenantToken = $tenantUser ? $tenantUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];
                        if (!empty($fcmTenantToken)) {
                            $firebases = new Firebases();
                            $firebases
                                ->withNotification("Pesanan KASIR-{$cashier->order_tenant} Berhasil Dibayar", "Pesanan {$cashier->id}, segera diproses!")
                                ->withData([
                                    'title' => "Pesanan KASIR-{$cashier->order_tenant} Berhasil Dibayar",
                                    'body' => "Pesanan {$cashier->id}, segera diproses!",
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                                ])->sendToTenant($fcmTenantToken);
                        }
                    }
                } elseif ($transactionStatus === 'pending') {
                    $checkout->update(['status_bayar' => 'pending']);
                } elseif (in_array($transactionStatus, ['deny', 'cancel', 'expire'])) {
                    $checkout->update(['status_bayar' => 'failed']);
                    Log::info("Checkout ID {$checkout->id} status updated to failed by Midtrans ({$transactionStatus}).");

                    $transaksi = Transaksi::find($checkout->transaksi_id);
                    if ($transaksi && $transaksi->status === 'pending') {
                        $transaksi->status = 'gagal_bayar';
                        $transaksi->save();
                    }

                    if ($transaksi && $transaksi->multitenant_id) {
                        Transaksi::where('multitenant_id', $transaksi->multitenant_id)
                            ->where('status', 'pending')
                            ->update(['status' => 'gagal_bayar']);
                    }
                } else {
                    $checkout->update(['status_bayar' => 'unknown']);
                }
            });
            return response()->json(['message' => 'OK Checkout'], 200);
        }

        $transaction = TopUp::where('midtrans_request_id', $orderId)->first();
        if (!$transaction) {
            Log::error('Transaksi tidak ditemukan untuk order_id: ' . $orderId);
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        if (in_array($transactionStatus, ['capture', 'settlement'])) {
            DB::transaction(function () use ($transaction, $json) {
                $settlementTime = $json['settlement_time'] ?? now();
                if ($transaction->isTf == 1) {
                    Log::info("TopUp {$transaction->id} sudah diproses sebelumnya, skip.");
                    return;
                }

                $transaction->status_bayar = 'settlement';
                $transaction->isTf = 1;
                $transaction->tgl_bayar = $settlementTime;
                $transaction->save();

                $saldo = SaldoKoin::firstOrCreate(
                    ['user_id' => $transaction->user_id],
                    ['jumlah' => 0]
                );
                $saldo->jumlah += $transaction->nominal;
                $saldo->save();

                TransaksiSaldoKoin::create([
                    'user_id' => $transaction->user_id,
                    'jumlah' => $transaction->nominal,
                    'tipe' => 'masuk',
                    'deskripsi' => 'Top-up berhasil melalui QRIS',
                ]);

                $user = User::with('fcmTokens')->find($transaction->user_id);
                $fcmUserToken = $user ? $user->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];
                if (!empty($fcmUserToken)) {
                    $firebases = new Firebases();
                    $firebases
                        ->withNotification('Top-up Berhasil', 'Saldo berhasil ditambahkan melalui QRIS.')
                        ->withData([
                            'title' => 'Top-up Berhasil',
                            'body' => 'Saldo berhasil ditambahkan melalui QRIS.',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ])->sendToFallback($fcmUserToken);
                }

                Log::info("TopUp {$transaction->id} berhasil diproses & saldo ditambahkan.");
            });
        } elseif ($transactionStatus === 'pending') {
            $transaction->update(['status_bayar' => 'pending']);
        } elseif (in_array($transactionStatus, ['deny', 'cancel', 'expire'])) {
            $transaction->update(['status_bayar' => 'failed']);
        } else {
            $transaction->update(['status_bayar' => 'unknown']);
        }
        return response()->json(['message' => 'OK TopUp'], 200);
    }
}
