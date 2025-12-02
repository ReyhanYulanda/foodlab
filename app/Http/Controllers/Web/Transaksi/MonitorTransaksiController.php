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

            if ($transaksi->multitenant_id) {
                Log::info("Transaksi #{$transaksi->id} membatalkan pesanan multitenant #{$transaksi->multitenant_id}.");

                // Cek apakah masih ada transaksi aktif dalam grup multitenant
                $stillActive = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
                    ->whereIn('status', ['pesanan_masuk', 'pesanan_diproses', 'siap_diantar', 'diantar'])
                    ->exists();

                // === cek apakah ini adalah tenant PERTAMA yang melakukan refund (first-cancel) ===
                $otherRefundCount = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
                    ->where('id', '!=', $transaksi->id)
                    ->where('status', 'refund_selesai')
                    ->count();

                $isFirstCancel = ($otherRefundCount === 0);

                if ($isFirstCancel) {
                    Log::info("Transaksi #{$transaksi->id} adalah tenant pertama yang cancel pada multitenant #{$transaksi->multitenant_id}.");

                    // Cari related transaksi lain dalam grup yang MASIH AKTIF (bukan refund)
                    $related = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
                        ->where('id', '!=', $transaksi->id)
                        ->where('status', '!=', 'refund_selesai') // Yang belum refund
                        ->first();

                    if ($related) {
                        Log::info("🔄 Found related transaction #{$related->id} (status: {$related->status})");

                        // Tentukan mana yang cancel dan mana yang tetap aktif
                        $cancelTx = $transaksi;      // status sudah refund_selesai
                        $activeTx = $related;        // status masih aktif (pesanan_masuk/diproses/dll)

                        // Hitung items
                        $activeItems = $activeTx->listTransaksiDetail->sum('jumlah');  // items yang tetap aktif
                        $cancelItems = $cancelTx->listTransaksiDetail->sum('jumlah');  // items yang dicancel

                        // Hitung X berdasarkan rumus
                        $totalItems = $activeItems + $cancelItems;

                        if ($activeItems <= 10) {
                            // Case: activeItems ≤ 10
                            $x = ($totalItems - 10) * 500;
                        } else {
                            // Case: activeItems > 10  
                            $x = ($totalItems - 10) * 500 - (($activeItems - 10) * 500);
                        }
                        $x = max($x, 0); // Pastikan X tidak negatif

                        Log::info("📊 Items calculation: active={$activeItems}, cancel={$cancelItems}, total={$totalItems}, X={$x}");

                        // PERBAIKAN: Cek kondisi untuk menentukan perlu swap atau tidak
                        // Swap hanya dilakukan jika ongkir cancel > ongkir active
                        $needSwap = ($cancelTx->ongkos_kirim > $activeTx->ongkos_kirim);

                        $cancelOngkirMulti = $cancelTx->ruangan->gedung->ongkir_multitenant ?? 0;
                        $activeOngkirMulti = $activeTx->ruangan->gedung->ongkir_multitenant ?? 0;
                        $activeOngkirPriority = Pengaturan::where('nama', 'ongkos_kirim_prioritas')->value('nilai') ?? 3000;
                        $activeBaseOngkir = $activeTx->ruangan->gedung->ongkir ?? 0;

                        if ($needSwap && $cancelTx->ongkos_kirim !== $activeTx->ongkos_kirim) {
                            // 🔄 SWAP ONGKIR: Hanya jika ongkir cancel lebih besar
                            $tempOngkir = $cancelTx->ongkos_kirim;
                            $cancelTx->ongkos_kirim = $activeTx->ongkos_kirim;
                            $activeTx->ongkos_kirim = $tempOngkir;

                            Log::info("🔄 SWAP: transaksi #{$cancelTx->id} ({$tempOngkir}) <-> #{$activeTx->id} ({$activeTx->ongkos_kirim})");

                            // Jika totalItems > 10, kurangi ongkir activeTx dengan X
                            if ($totalItems > 10) {
                                $newOngkir = max($activeTx->ongkos_kirim - $x, 0);
                                Log::info("📉 Kurangi X={$x} untuk transaksi aktif #{$activeTx->id}: {$activeTx->ongkos_kirim} -> {$newOngkir}");
                                $activeTx->ongkos_kirim = $newOngkir;
                            }

                            $cancelTx->total = $cancelTx->sub_total + $cancelOngkirMulti;
                            if ($transaksi->isPriority) {
                                $activeTx->total = ($activeTx->sub_total + $activeBaseOngkir + $activeOngkirPriority + $this->extraFee($totalItems)) - $x;
                            } else {
                                $activeTx->total = ($activeTx->sub_total + $activeBaseOngkir + $this->extraFee($totalItems)) - $x;
                            }

                            $cancelTx->save();
                            $activeTx->save();

                            Log::info("✅ [SWAP DONE] Swap completed for multitenant #{$transaksi->multitenant_id}");
                            Log::info("   Cancel #{$cancelTx->id} ongkir: {$cancelTx->ongkos_kirim}");
                            Log::info("   Active #{$activeTx->id} ongkir: {$activeTx->ongkos_kirim}");
                        } else {
                            Log::info("ℹ️ Tidak perlu swap, cek kondisi:");
                            Log::info("   - Cancel ongkir (#{$cancelTx->id}): {$cancelTx->ongkos_kirim}");
                            Log::info("   - Active ongkir (#{$activeTx->id}): {$activeTx->ongkos_kirim}");
                            Log::info("   - Need swap: " . ($needSwap ? 'YES' : 'NO'));

                            // PERBAIKAN: JIKA TIDAK SWAP, tetap kurangi X dari ongkir active jika totalItems > 10
                            if ($totalItems > 10) {
                                // Tapi tunggu! Jika tidak swap, mungkin X perlu dikurangi dari ongkir yang lebih besar?
                                // Sesuai case 2: ongkir besar ada di cancel (10500), kecil di active (0)
                                // Maka kurangi X dari ongkir cancel karena dia yang lebih besar

                                if ($cancelTx->ongkos_kirim > $activeTx->ongkos_kirim) {
                                    // Ongkir besar di cancel, kecil di active
                                    // Kurangi X dari cancel karena dialah yang lebih besar
                                    $newOngkir = max($cancelTx->ongkos_kirim - $x, 0);
                                    Log::info("📉 Kurangi X={$x} dari cancel (besar) #{$cancelTx->id}: {$cancelTx->ongkos_kirim} -> {$newOngkir}");
                                    $cancelTx->ongkos_kirim = $newOngkir;
                                } else {
                                    // Ongkir besar di active, kecil di cancel
                                    // Kurangi X dari active karena dialah yang lebih besar
                                    $newOngkir = max($activeTx->ongkos_kirim - $x, 0);
                                    Log::info("📉 Kurangi X={$x} dari active (besar) #{$activeTx->id}: {$activeTx->ongkos_kirim} -> {$newOngkir}");
                                    $activeTx->ongkos_kirim = $newOngkir;
                                }

                                $cancelTx->total = $cancelTx->sub_total + $cancelOngkirMulti;
                                if ($transaksi->isPriority) {
                                    $activeTx->total = $activeTx->sub_total + $activeBaseOngkir + $activeOngkirPriority + $this->extraFee($totalItems);
                                } else {
                                    $activeTx->total = $activeTx->sub_total + $activeBaseOngkir + $this->extraFee($totalItems);
                                }

                                $cancelTx->save();
                                $activeTx->save();
                            } else {
                                Log::info("ℹ️ Total items ≤ 10, no X to apply");
                            }
                        }
                    } else {
                        Log::info("ℹ️ Tidak ada transaksi aktif lain dalam multitenant #{$transaksi->multitenant_id}");
                    }
                } else {
                    Log::info("ℹ️ Transaksi #{$transaksi->id} bukan tenant pertama yang cancel");
                }

                // Jika semua transaksi sudah refund/selesai → tenant terakhir yang cancel (flow existing)
                if (!$stillActive) {
                    Log::info("🎯 All transactions in multitenant #{$transaksi->multitenant_id} are now refunded/completed");

                    // --- existing flow (total refund, kembalikan voucher/cashback, tambah saldo koin) ---
                    $totalRefund = Transaksi::where('multitenant_id', $transaksi->multitenant_id)->sum('total');

                    // Tambahkan ke saldo koin user
                    $saldo = \App\Models\SaldoKoin::firstOrCreate(['user_id' => $transaksi->user_id]);
                    $saldo->jumlah += $totalRefund;
                    $saldo->save();

                    // Catat transaksi saldo koin
                    \App\Models\TransaksiSaldoKoin::create([
                        'user_id'   => $transaksi->user_id,
                        'jumlah'    => $totalRefund,
                        'tipe'      => 'masuk',
                        'deskripsi' => 'Refund pesanan multitenant #' . $transaksi->multitenant_id,
                    ]);

                    // Kembalikan voucher & cashback jika semua refund
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

                    $this->sendMultitenantRefundNotification($transaksi, $totalRefund, $firebases);
                }
            }

            if ($transaksi->isPriority) {
                // Kirim FCM saldo kembalian
                if ($transaksi->driver_id == null) {
                    $masbroTokens = User::role('masbro')
                        // ->where('isOnline', 1)
                        ->with('fcmTokens')
                        ->get()
                        ->flatMap(fn($user) => $user->fcmTokens->pluck('fcm_token'))
                        ->filter()
                        ->unique()
                        ->values()
                        ->toArray();

                    $fcmMasbroToken = $masbroTokens;
                    if (!empty($fcmMasbroToken)) {
                        $firebases
                            ->withNotification('Pesanan prioritas', "Salah satu pesanan prioritas  dibatalkan #{$transaksi->id}")
                            ->withData([
                                'title' => 'Pesanan Prioritas',
                                'body' => "Salah satu pesanan prioritas  dibatalkan #{$transaksi->id}",
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            ])->sendToFallback($fcmMasbroToken);
                        Log::info('Sending FCM to driver', ['tokens' => $fcmMasbroToken]);
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

                    $fcmMasbroToken = $masbroTokens;
                    if (!empty($fcmMasbroToken)) {
                        $firebases
                            ->withNotification('Pesanan prioritas', "Salah satu pesanan prioritas  dibatalkan #{$transaksi->id}")
                            ->withData([
                                'title' => 'Pesanan Prioritas',
                                'body' => "Salah satu pesanan prioritas  dibatalkan #{$transaksi->id}",
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            ])->sendToDriver($fcmMasbroToken);
                        Log::info('Sending FCM to driver', ['tokens' => $fcmMasbroToken]);
                    }
                }
            }

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

    public function resetDriver($id, Firebases $firebases)
    {
        DB::beginTransaction();
        try {
            $transaksi = Transaksi::find($id);

            if (!$transaksi) {
                return redirect()->back()->with('error', 'Transaksi tidak ditemukan.');
            }

            if (!in_array($transaksi->status, ['siap_diantar', 'diantar'])) {
                return redirect()->back()->with('error', 'Status pesanan tidak valid untuk reset driver.');
            }

            if ($transaksi->driver_id === null) {
                return redirect()->back()->with('error', 'Driver sudah kosong, tidak bisa di-reset.');
            }

            /**
             * =============================
             * 🔥 LOGIKA MULTITENANT
             * =============================
             */
            if ($transaksi->multitenant_id) {

                $group = Transaksi::where('multitenant_id', $transaksi->multitenant_id)->get();

                $adaProses = $group->contains(fn($t) => $t->status === 'pesanan_diproses');

                foreach ($group as $t) {

                    $t->driver_id = null;

                    if ($adaProses) {
                        if ($t->status === 'diantar') {
                            $t->status = 'siap_diantar';
                        }
                    } else {
                        if (in_array($t->status, ['diantar', 'pesanan_diproses'])) {
                            $t->status = 'siap_diantar';
                        }
                    }

                    $t->save();
                }
            } else {
                // Non-multitenant
                $transaksi->driver_id = null;
                $transaksi->status = 'siap_diantar';
                $transaksi->save();
            }


            /**
             * ===================================================
             * 🔥 PERSIAPAN TOKENS DRIVER ONLINE & OFFLINE
             * ===================================================
             */

            $masbroTokens = User::role('masbro')
                ->where('isOnline', 1)
                ->with('fcmTokens')
                ->get()
                ->flatMap(fn($user) => $user->fcmTokens->pluck('fcm_token'))
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $masbroOfflineTokens = User::role('masbro')
                ->where('isOnline', 0)
                ->with('fcmTokens')
                ->get()
                ->flatMap(fn($user) => $user->fcmTokens->pluck('fcm_token'))
                ->filter()
                ->unique()
                ->values()
                ->toArray();


            /**
             * ===================================================
             * 🔥 FUNCTION UNTUK SEND NOTIF ONLINE
             * ===================================================
             */
            $sendToDrivers = function ($title, $body, $type) use (
                $firebases,
                $transaksi,
                $masbroTokens
            ) {
                if (!empty($masbroTokens)) {
                    $firebases->withNotification($title, $body)
                        ->withData([
                            'title'          => $title,
                            'body'           => $body,
                            'type'           => $type,
                            'transaksi_id'   => $transaksi->id,
                            'click_action'   => 'FLUTTER_NOTIFICATION_CLICK',
                        ])
                        ->sendToDriver($masbroTokens);
                }
            };


            /**
             * ===================================================
             * 🔥 FUNCTION UNTUK SEND NOTIF OFFLINE
             * ===================================================
             */
            $sendToOfflineDrivers = function ($title, $body, $type) use (
                $firebases,
                $transaksi,
                $masbroOfflineTokens
            ) {
                if (!empty($masbroOfflineTokens)) {
                    $firebases->withNotification($title, $body)
                        ->withData([
                            'title'          => $title,
                            'body'           => $body,
                            'type'           => $type,
                            'transaksi_id'   => $transaksi->id,
                            'click_action'   => 'FLUTTER_NOTIFICATION_CLICK',
                        ])
                        ->sendToFallback($masbroOfflineTokens);
                }
            };


            /**
             * ===================================================
             * 🔥 KIRIM NOTIF SETELAH STATUS JADI SIAP DIANTAR
             * ===================================================
             */

            if ($transaksi->status === 'siap_diantar') {

                // ONLINE
                $sendToDrivers(
                    'Ada Pesanan Siap Diantar',
                    "Pesanan {$transaksi->id} sudah siap. Yuk, ambil dan antar sekarang!",
                    'siap_diantar_driver'
                );

                // OFFLINE
                $sendToOfflineDrivers(
                    'Ada Pesanan Siap Diantar Loh',
                    "Pesanan ke {$transaksi->id}. Yuk, nyalain status drivermu!",
                    'siap_diantar_driver'
                );
            }


            DB::commit();
            return redirect()->back()->with('success', "Driver berhasil di-reset. Notifikasi terkirim ke driver.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Reset driver gagal: " . $e->getMessage());
            return redirect()->back()->with('error', 'Reset driver gagal.');
        }
    }

    private function extraFee($totalItems)
    {
        $extraLimit = 10;
        $costPerExtra = 500;

        // Jika current items > 10, hanya kelebihan dari 10 yang kena extra fee
        if ($totalItems > $extraLimit) {
            return ($totalItems - $extraLimit) * $costPerExtra;
        }

        return 0;
    }
}
