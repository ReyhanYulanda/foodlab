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

            if ($request->has('catatan_penolakan')) {
                $transaksi->catatan_penolakan = $request->input('catatan_penolakan');
            }

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

            $transaksi->status = 'pesanan_ditolak';
            $transaksi->save();

            $fcmUser = User::with('fcmTokens')->find($transaksi->user_id);
            $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];

            $userTransaksi = $transaksi->user;
            if ($userTransaksi && $userTransaksi->fcm_token) {
                $firebases
                    ->withNotification('Pesanan Dibatalkan', "{$transaksi->catatan_penolakan}")
                    ->withData([
                        'title' => 'Pesanan Dibatalkan',
                        'body' => "{$transaksi->catatan_penolakan}",
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                    ])->sendToFallback($fcmUserToken);
            }

            try {
                $transaksi->refundKoin();

                TransaksiSaldoKoin::create([
                    'user_id' => $transaksi->user_id,
                    'jumlah' => $transaksi->total,
                    'tipe' => 'masuk',
                    'deskripsi' => 'Refund pesanan #' . $transaksi->id,
                ]);

                $transaksi->status = 'refund_selesai';
                $transaksi->save();

                if ($userTransaksi && $userTransaksi->fcm_token) {
                    $firebases
                        ->withNotification('Refund Berhasil', 'Koin dari pesanan #' . $transaksi->id . ' telah berhasil dikembalikan ke akun kamu.')
                        ->withData([
                            'title' => 'Refund Berhasil',
                            'body' => 'Koin dari pesanan #' . $transaksi->id . ' telah berhasil dikembalikan ke akun kamu.',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                        ])->sendToFallback($fcmUserToken);
                }

                DB::commit();
                return redirect()->back()->with('success', "Transaksi #{$transaksi->id} dibatalkan dan refund berhasil.");
            } catch (\Throwable $e) {
                $transaksi->status = 'refund_gagal';
                $transaksi->save();

                DB::commit();
                Log::warning("Refund gagal: " . $e->getMessage());
                return redirect()->back()->with('error', "Transaksi dibatalkan, tapi refund gagal. Silakan hubungi admin.");
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error("Gagal membatalkan transaksi: " . $th->getMessage());
            return redirect()->back()->with('error', 'Gagal membatalkan transaksi.');
        }
    }
}
