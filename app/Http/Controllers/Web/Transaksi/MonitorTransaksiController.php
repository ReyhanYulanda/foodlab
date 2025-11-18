<?php

namespace App\Http\Controllers\Web\Transaksi;

use App\Http\Controllers\Controller;
use App\Models\CatatVoucher;
use Illuminate\Support\Facades\DB;
use App\Models\Transaksi;
use App\Models\Pengaturan;
use App\Models\TransaksiSaldoKoin;
use App\Models\User;
use App\Services\Firebases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MonitorTransaksiController extends Controller
{
    public function monitorPesanan(Request $request)
    {
        $transaksi = Transaksi::whereIn('status', [
            'pesanan_masuk',
            'pesanan_diproses',
            'siap_diantar',
            'siap_diambil',
            'diantar'
        ])
            ->latest()
            ->paginate(10);

        return view('pages.transaksi.monitor-pesanan.index', compact('transaksi'));
    }

    public function postCancel(Request $request, $id, Firebases $firebases)
    {
        DB::beginTransaction();
        try {
            $currentUser = $request->user();

            if (!$currentUser->can('cancel order')) {
                return redirect()->back()->with('error', 'Tidak memiliki akses.');
            }

            $transaksi = Transaksi::find($id);

            if (!$transaksi) {
                return redirect()->back()->with('error', 'Transaksi tidak ditemukan.');
            }

            if (in_array($transaksi->status, ['refund_selesai', 'refund_diproses'])) {
                return redirect()->back()->with('error', 'Transaksi sudah direfund sebelumnya.');
            }

            if ($transaksi->status === 'refund_gagal') {
                return redirect()->back()->with('error', 'Refund sebelumnya gagal. Silakan hubungi admin.');
            }

            $isTenant = $currentUser->id == $transaksi->tenant_id
                && $currentUser->can('tenant cancel order');

            $isAdmin = $currentUser->can('admin cancel order');

            if ($transaksi->status === 'pesanan_diproses' && !($isAdmin || $isTenant)) {
                return redirect()->back()->with('error', 'Pesanan sedang diproses. Tidak bisa dibatalkan.');
            }

            if (
                in_array($transaksi->status, ['siap_diantar', 'siap_diambil', 'diantar'])
                && !$isAdmin
            ) {
                return redirect()->back()->with('error', 'Pesanan sedang diproses. Tidak bisa dibatalkan.');
            }

            // Catatan penolakan
            if ($request->has('catatan_penolakan')) {
                $transaksi->catatan_penolakan = $request->input('catatan_penolakan');
            }

            // ======================
            // 1. CANCEL BUKAN MULTITENANT
            // ======================
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

                // Mark as ditolak → refund
                $transaksi->status = 'pesanan_ditolak';
                $transaksi->save();

                // Notifikasi pembatalan
                $this->sendCancelNotification($transaksi, $firebases);

                try {
                    // Refund saldo
                    $transaksi->refundKoin();

                    TransaksiSaldoKoin::create([
                        'user_id' => $transaksi->user_id,
                        'jumlah' => $transaksi->total,
                        'tipe' => 'masuk',
                        'deskripsi' => 'Refund pesanan #' . $transaksi->id,
                    ]);

                    $transaksi->status = 'refund_selesai';
                    $transaksi->save();

                    $this->sendRefundSuccessNotification($transaksi, $firebases);

                    DB::commit();
                    return redirect()->back()->with('success', "Transaksi #{$transaksi->id} dibatalkan dan refund berhasil.");
                } catch (\Throwable $e) {
                    $transaksi->status = 'refund_gagal';
                    $transaksi->save();

                    DB::commit();
                    Log::warning("Refund gagal: " . $e->getMessage());
                    return redirect()->back()->with('error', "Transaksi dibatalkan, tapi refund gagal. Silakan hubungi admin.");
                }
            }

            // ======================
            // 2. CANCEL MULTITENANT
            // ======================

            // Set transaksi ini menjadi refund_selesai
            $transaksi->status = 'refund_selesai';
            $transaksi->save();

            $this->sendCancelNotification($transaksi, $firebases);

            // Cek apakah masih ada transaksi aktif di grup
            $stillActive = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
                ->whereIn('status', ['pesanan_masuk', 'pesanan_diproses', 'siap_diantar', 'diantar'])
                ->exists();

            // Jika masih ada transaksi aktif → STOP di sini
            if ($stillActive) {
                DB::commit();
                return redirect()->back()->with('success', "Transaksi #{$transaksi->id} berhasil dibatalkan (refund_selesai).");
            }

            // Tidak ada yang aktif → berarti ini tenant terakhir → refund total!
            $totalRefund = Transaksi::where('multitenant_id', $transaksi->multitenant_id)->sum('total');

            // Tambahkan saldo
            $saldo = \App\Models\SaldoKoin::firstOrCreate(['user_id' => $transaksi->user_id]);
            $saldo->jumlah += $totalRefund;
            $saldo->save();

            TransaksiSaldoKoin::create([
                'user_id'   => $transaksi->user_id,
                'jumlah'    => $totalRefund,
                'tipe'      => 'masuk',
                'deskripsi' => 'Refund pesanan multitenant #' . $transaksi->multitenant_id,
            ]);

            // Kembalikan voucher jika ada di salah satu transaksi multitenant
            $transaksiVoucher = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
                ->whereNotNull('voucher_id')
                ->first();

            if ($transaksiVoucher && $transaksiVoucher->voucher) {
                CatatVoucher::where('transaksi_id', $transaksiVoucher->id)->delete();
                $voucher = $transaksiVoucher->voucher;

                $voucher->increment('quantity');
                if ($voucher->cashback) {
                    $voucher->cashback->increment('quantity');
                }
            }

            // Kirim FCM saldo kembalian
            $this->sendMultitenantRefundNotification($transaksi, $totalRefund, $firebases);

            DB::commit();
            return redirect()->back()->with('success', "Semua pesanan multitenant telah dibatalkan dan saldo dikembalikan!");
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error("Gagal membatalkan transaksi: " . $th->getMessage());
            return redirect()->back()->with('error', 'Gagal membatalkan transaksi.');
        }
    }


    // ======================
    // ✉️ Helper Notifikasi
    // ======================

    private function sendCancelNotification($transaksi, $firebases)
    {
        $user = User::with('fcmTokens')->find($transaksi->user_id);
        if (!$user) return;

        $tokens = $user->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray();

        if (!empty($tokens)) {
            $firebases
                ->withNotification('Pesanan Dibatalkan', "{$transaksi->catatan_penolakan}")
                ->withData([
                    'title' => 'Pesanan Dibatalkan',
                    'body'  => "{$transaksi->catatan_penolakan}",
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                ])
                ->sendToFallback($tokens);
        }
    }

    private function sendRefundSuccessNotification($transaksi, $firebases)
    {
        $user = User::with('fcmTokens')->find($transaksi->user_id);
        if (!$user) return;

        $tokens = $user->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray();

        if (!empty($tokens)) {
            $firebases
                ->withNotification('Refund Berhasil', 'Koin dari pesanan #' . $transaksi->id . ' telah dikembalikan.')
                ->withData([
                    'title' => 'Refund Berhasil',
                    'body'  => 'Koin dari pesanan #' . $transaksi->id . ' telah dikembalikan.',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                ])
                ->sendToFallback($tokens);
        }
    }

    private function sendMultitenantRefundNotification($transaksi, $totalRefund, $firebases)
    {
        $user = User::with('fcmTokens')->find($transaksi->user_id);
        if (!$user) return;

        $tokens = $user->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray();

        if (!empty($tokens)) {
            $firebases
                ->withNotification(
                    'Pesanan Multitenant Dibatalkan',
                    "Semua pesanan multitenant #{$transaksi->multitenant_id} telah dibatalkan. Saldo Rp " . number_format($totalRefund, 0, ',', '.') . " telah dikembalikan."
                )
                ->withData([
                    'title' => 'Pesanan Multitenant Dibatalkan',
                    'body'  => "Saldo Rp " . number_format($totalRefund, 0, ',', '.') . " telah dikembalikan.",
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                ])
                ->sendToFallback($tokens);
        }
    }
}
