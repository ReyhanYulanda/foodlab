<?php

namespace App\Services\Transaksi\Actions;

use App\Response\ResponseApi;
use App\Models\CatatVoucher;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\Firebases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CancelOrderAction
{
    private $firebases;

    public function __construct(Firebases $firebases)
    {
        $this->firebases = $firebases;
    }

    public function execute(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $currentUser = $request->user();

            if (!$currentUser->can('cancel order')) {
                return ResponseApi::forbidden('tidak memiliki akses');
            }

            $transaksi = Transaksi::find($id);

            if (!$transaksi) {
                return ResponseApi::error("Transaksi tidak ditemukan", 404);
            }

            if (in_array($transaksi->status, ['refund_selesai', 'refund_diproses'])) {
                return ResponseApi::error("Transaksi sudah direfund sebelumnya", 400);
            }

            if ($transaksi->status === 'refund_gagal') {
                return ResponseApi::error("Refund sebelumnya gagal. Silakan hubungi admin", 400);
            }


            $isTenant = $currentUser->id == $transaksi->tenant_id
                && $currentUser->can('tenant cancel order');

            $isAdmin = $currentUser->can('admin cancel order');

            if (
                $transaksi->status === 'pesanan_diproses' &&
                !($isAdmin || $isTenant)
            ) {
                return ResponseApi::error("Pesanan sedang diproses. Tidak bisa dibatalkan", 400);
            }

            if (
                $transaksi->status === 'siap_diantar' || $transaksi->status === 'siap_diambil' || $transaksi->status === 'diantar' &&
                !($isAdmin)
            ) {
                return ResponseApi::error("Pesanan sedang diproses. Tidak bisa dibatalkan", 400);
            }

            if ($request->has('catatan_penolakan')) {
                $transaksi->catatan_penolakan = $request->input('catatan_penolakan');
            }

            if ($transaksi->multitenant_id === null) {
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

            $transaksi->status = 'pesanan_ditolak';
            $transaksi->save();

            $fcmUser = User::with('fcmTokens')->find($transaksi->user_id);
            $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];


            $userTransaksi = $transaksi->user;
            if ($userTransaksi && $userTransaksi->fcm_token) {
                $this->firebases
                    ->withNotification('Pesanan Dibatalkan', "{$transaksi->catatan_penolakan}")
                    ->withData([
                        'title' => 'Pesanan Dibatalkan',
                        'body' => "{$transaksi->catatan_penolakan}",
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                    ])->sendToFallback($fcmUserToken);
            }

            // === LOGIKA MULTITENANT ===
            try {
                // 🔸 Pastikan transaksi masih bisa dibatalkan
                if (in_array($transaksi->status, ['selesai', 'refund_selesai'])) {
                    return ResponseApi::error("Pesanan tidak dapat dibatalkan karena sudah selesai atau sudah direfund.", 400);
                }

                // 🔸 Update status transaksi ini ke refund_selesai
                $transaksi->status = 'refund_selesai';
                $transaksi->save();

                // 🔹 Kirim notifikasi FCM ke user (jika ada)
                $userTransaksi = User::with('fcmTokens')->find($transaksi->user_id);
                $fcmUserToken = $userTransaksi ? $userTransaksi->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];

                if (!empty($fcmUserToken)) {
                    $this->firebases
                        ->withNotification('Pesanan Dibatalkan', "Pesanan #{$transaksi->id} telah dibatalkan dan status berubah menjadi refund_selesai.")
                        ->withData([
                            'title' => 'Pesanan Dibatalkan',
                            'body' => "Pesanan #{$transaksi->id} telah dibatalkan dan status berubah menjadi refund_selesai.",
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                        ])
                        ->sendToFallback($fcmUserToken);
                }

                // 🔹 Jika ada multitenant_id, lakukan pengecekan tambahan
                if ($transaksi->multitenant_id) {
                    Log::info("Transaksi #{$transaksi->id} membatalkan pesanan multitenant #{$transaksi->multitenant_id}.");

                    // Cek apakah masih ada transaksi aktif dalam grup multitenant
                    $stillActive = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
                        ->whereIn('status', ['pesanan_masuk', 'pesanan_diproses', 'siap_diantar', 'diantar'])
                        ->exists();

                    // Jika semua transaksi sudah refund/selesai → tenant terakhir yang cancel
                    if (!$stillActive) {
                        // Hitung total refund semua transaksi dalam grup multitenant
                        $totalRefund = Transaksi::where('multitenant_id', $transaksi->multitenant_id)->sum('total');

                        // Tambahkan ke saldo koin user
                        $saldo = \App\Models\SaldoKoin::firstOrCreate(['user_id' => $transaksi->user_id]);
                        $saldo->jumlah += $totalRefund;
                        $saldo->save();

                        // Catat transaksi saldo koin
                        \App\Models\TransaksiSaldoKoin::create([
                            'user_id' => $transaksi->user_id,
                            'jumlah' => $totalRefund,
                            'tipe' => 'masuk',
                            'deskripsi' => 'Refund pesanan multitenant #' . $transaksi->multitenant_id,
                        ]);

                        // ✅ Tambahan: kembalikan voucher & cashback jika semua refund
                        $transaksiDenganVoucher = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
                            ->whereNotNull('voucher_id')
                            ->first();

                        if ($transaksiDenganVoucher && $transaksiDenganVoucher->voucher_id) {
                            $voucher = $transaksiDenganVoucher->voucher;

                            if ($voucher) {
                                // Hapus catatan voucher
                                CatatVoucher::where('transaksi_id', $transaksiDenganVoucher->id)->delete();

                                // Kembalikan quantity voucher
                                $voucher->increment('quantity');

                                // Kembalikan quantity cashback (jika ada relasi)
                                if ($voucher->cashback) {
                                    $voucher->cashback->increment('quantity');
                                }

                                Log::info("🔁 Voucher #{$voucher->id} dikembalikan karena semua transaksi multitenant #{$transaksi->multitenant_id} refund.");
                            }
                        }

                        // Kirim notifikasi ke user
                        if (!empty($fcmUserToken)) {
                            $this->firebases
                                ->withNotification(
                                    'Pesanan Multitenant Dibatalkan',
                                    "Semua pesanan multitenant #{$transaksi->multitenant_id} telah dibatalkan. Saldo sebesar Rp " . number_format($totalRefund, 0, ',', '.') . " telah dikembalikan."
                                )
                                ->withData([
                                    'title' => 'Pesanan Multitenant Dibatalkan',
                                    'body' => "Saldo Rp " . number_format($totalRefund, 0, ',', '.') . " telah dikembalikan ke akun Anda.",
                                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                                ])
                                ->sendToFallback($fcmUserToken);
                        }

                        Log::info("Multitenant #{$transaksi->multitenant_id} seluruhnya telah dibatalkan. Total refund: {$totalRefund}");
                    }
                }

                DB::commit();
                return ResponseApi::success(null, "Pesanan berhasil dibatalkan (refund_selesai)");
            } catch (\Throwable $e) {
                DB::rollBack();
                $transaksi->status = 'refund_gagal';
                $transaksi->save();
                Log::warning("Refund gagal: " . $e->getMessage());
                return ResponseApi::error("Transaksi dibatalkan, tapi refund gagal. Silakan hubungi admin.");
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error("Gagal membatalkan transaksi: " . $th->getMessage());
            return ResponseApi::serverError();
        }
    }
}
