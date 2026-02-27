<?php

namespace App\Services\Transaksi\Actions;

use App\Models\Menus;
use App\Models\Pengaturan;
use App\Models\Ruangan;
use App\Models\SaldoKoin;
use App\Models\Transaksi;
use App\Models\TransaksiSaldoKoin;
use App\Models\User;
use App\Models\Voucher;
use App\Models\CatatVoucher;
use App\Models\Checkout;
use App\Helper\TransaksiCek;
use App\Helpers\TransaksiHelper;
use App\Repositories\TransaksiRepository;
use App\Services\Firebases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CreateOrderAction
{
    private $firebases;
    private $transaksiRepo;

    public function __construct(Firebases $firebases, TransaksiRepository $transaksiRepo)
    {
        $this->firebases = $firebases;
        $this->transaksiRepo = $transaksiRepo;
    }

    public function execute(Request $request, User $user, $tenant, $menuFirst)
    {
        $menu_id = $request->menus[0]['id'];
        $tenantUser = User::with('fcmTokens')->whereHas('tenant', function ($tenant) use ($menu_id) {
            $tenant->whereHas('listMenu', function ($kelola) use ($menu_id) {
                $kelola->where('id', $menu_id);
            });
        })->first();

        $masbroTokens = User::role('masbro')
            // ->where('isOnline', 1)
            ->with('fcmTokens')
            ->get()
            ->flatMap(fn($user) => $user->fcmTokens->pluck('fcm_token'))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $fcmTenantToken = $tenantUser ? $tenantUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];
        $fcmMasbroToken = $masbroTokens;

        if (!$tenantUser) {
            Log::warning('User tenant tidak ditemukan berdasarkan menu_id', ['menu_id' => $menu_id]);
        }

        $status = @$request->status ?? (
            $request->metode_pembayaran == 'cod' || $request->metode_pembayaran == 'koin'
            ? "pesanan_masuk"
            : "pending"
        );

        $totalHargaMenu = 0;
        $totalJumlahMenu = 0;

        foreach ($request->menus as $menu) {
            $menuModel = Menus::withTrashed()->find($menu['id']); // DITAMBAHKAN withTrashed()
            if ($menuModel) {
                $totalHargaMenu += $menuModel->harga * $menu['jumlah'];
                $totalJumlahMenu += $menu['jumlah'];
            }
        }

        $ruanganId = $request->isAntar ? $request->ruangan_id : null;
        $ongkosKirim = 0;

        $isAntar = filter_var($request->input('isAntar'), FILTER_VALIDATE_BOOLEAN);
        $isPriority = filter_var($request->input('isPriority'), FILTER_VALIDATE_BOOLEAN);

        if ($isPriority && !$isAntar) {
            return response()->json([
                'status' => 'failed',
                'message' => ['Pengiriman prioritas hanya bisa dilakukan dengan pengiriman']
            ], 400);
        }

        if ($request->isAntar && $ruanganId) {
            $ruangan = Ruangan::with('gedung')->find($ruanganId);

            if ($ruangan && $ruangan->gedung) {
                $ongkosKirim = $ruangan->gedung->ongkir ?? 0;
            }
            $biayaExtra = Pengaturan::where('nama', 'biaya_extra')->value('nilai') ?? 500;
            if ($totalJumlahMenu > 10) {
                $ongkosKirim += ($totalJumlahMenu - 10) * $biayaExtra;
            }

            if ($isPriority) {
                $ongkirPrioritas = Pengaturan::where('nama', 'ongkos_kirim_prioritas')->value('nilai') ?? 3000;
                $ongkosKirim += $ongkirPrioritas;
            }
        }

        $biayaLayanan = Pengaturan::where('nama', 'biaya_layanan')->value('nilai');

        $totalFinal = $totalHargaMenu
            + ($request->isAntar ? $ongkosKirim : 0)
            + $biayaLayanan;

        if ($request->metode_pembayaran === 'koin') {
            $saldo = SaldoKoin::where('user_id', $user->id)->first();

            if (!$saldo || $saldo->jumlah < $totalFinal) {
                DB::rollBack();
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Saldo koin tidak cukup',
                ], 400);
            }
        }

        $voucherId = $request->input('voucher_id');
        $assignCashback = 0;

        if ($voucherId) {
            $voucher = Voucher::with('cashback')
                ->where('id', $voucherId)
                ->where('user_id', $user->id)
                ->first();

            if (!$voucher) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Voucher tidak valid'
                ], 400);
            }

            $cashback = $voucher->cashback;

            if (!$cashback) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Cashback tidak ditemukan'
                ], 400);
            }

            if ($cashback->quantity <= 0) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Cashback sudah habis'
                ], 400);
            }

            if (now()->gt($cashback->end_date)) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Cashback telah expired'
                ], 400);
            }

            if ($totalFinal < $cashback->minimal_beli) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Total belanja minimal ' . ($cashback->minimal_beli)
                ], 400);
            }

            if ($voucher->user_id != $user->id) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Voucher bukan milik anda'
                ], 400);
            }

            if ($voucher->quantity <= 0) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Voucher sudah habis'
                ], 400);
            }

            if (!$cashback->is_valid) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Cashback tidak valid'
                ], 400);
            }

            $assignCashback = $totalFinal * $cashback->value;
            $assignVoucherId = $voucher->id;

            if ($assignCashback > $cashback->max_cashback) {
                $assignCashback = $cashback->max_cashback;
            }

            $cashback->decrement('quantity');
            $voucher->decrement('quantity');
        }

        $ongkosKirimFix = $request->isAntar ? $ongkosKirim : 0;

        $userIdTenant = $tenant->user_id ?? $tenant->pemilik->id; // DITAMBAHKAN fallback

        $transaksi = Transaksi::create([
            'user_id' => $user->id,
            'total' => $totalFinal,
            'isAntar' => $request->isAntar,
            'isPriority' => $request->boolean('isPriority') ?? false,
            'metode_pembayaran' => $request->metode_pembayaran,
            'tenant_id' => $userIdTenant, // DITAMBAHKAN
            'ruangan_id' => $ruanganId,
            'catatan' => @$request->catatan,
            'status' => $status,
            'ongkos_kirim' => $ongkosKirimFix,
            'biaya_layanan' => $biayaLayanan,
            'catatan_lokasi_pengantaran' => $request->catatan_lokasi_pengantaran ?? null,
            'cashback_amount' => $assignCashback,
            'voucher_id' => $assignVoucherId ?? null
        ]);

        do {
            $kodePemesanan = TransaksiCek::generateKodePemesanan($transaksi->id);
        } while (Transaksi::where('kode_pemesanan', $kodePemesanan)->exists());

        // SIMPAN ke database
        $transaksi->kode_pemesanan = $kodePemesanan;
        $transaksi->save();

        if ($voucherId) {
            CatatVoucher::create([
                'user_id' => $user->id,
                'transaksi_id' => $transaksi->id,
                'voucher_id' => $voucher->id,
                'quantity_voucher' => $voucher->quantity,
                'cashback_amount' => $assignCashback,
            ]);
        }

        $success = app(\App\Http\Controllers\Transaksi\TransaksiController::class)->storeTransakasiDetail($request, $transaksi);

        if ($success) {
            DB::commit();

            if ($status == 'selesai') {
                return response()->json([
                    "status" => 'success',
                    'messages' => "transaksi berhasil dibuat",
                    "order_id" => $transaksi->id,
                    "data" => [
                        'transaksi' => $transaksi,
                        'tenant' => $tenant,
                    ]
                ], 201);
            }

            if ($transaksi->metode_pembayaran === 'qris') {
                $biaya = $this->generateBiayaAdmin((int) $totalFinal);
                $uuidParts = explode('-', Str::uuid()->toString());
                $shortUuid = implode('-', array_slice($uuidParts, 0, 3));
                $qrisTotalFinal = $biaya['total_biaya_admin'] + $totalFinal;

                $orderId = 'foodlabs-' . $shortUuid . '-' . time();

                $params = [
                    'transaction_details' => [
                        'order_id' => $orderId,
                        'gross_amount' => $qrisTotalFinal,
                    ],
                    'payment_type' => 'qris',
                    'qris' => [
                        'acquirer' => 'gopay'
                    ],
                ];
                \Midtrans\Config::$serverKey = config('custom.midtrans_server_key');
                \Midtrans\Config::$isProduction = false;
                \Midtrans\Config::$isSanitized = true;
                \Midtrans\Config::$is3ds = true;
                $snap = \Midtrans\CoreApi::charge($params);

                Checkout::create([
                    'user_id' => $user->id,
                    'transaksi_id' => $transaksi->id,
                    'nominal' => $totalFinal,
                    'biaya_midtrans' => $biaya['biaya_midtrans'],
                    'biaya_ubisma' => $biaya['biaya_ubsima'],
                    'total_biaya_admin' => $biaya['total_biaya_admin'],
                    'total_bayar_user' => $biaya['total_bayar_user'],
                    'status_bayar' => 'pending',
                    'midtrans_request_id' => $orderId,
                    'kode_bayar' => $snap->actions[0]->url ?? null,
                    'tgl_akhir_tagihan' => $snap->expiry_time ?? null,
                ]);

                $extraQris = [
                    'order_id_midtrans' => $orderId,
                    'qr_url' => $snap->actions[0]->url ?? null,
                    'expiry' => $snap->expiry_time ?? null,
                    'biaya_admin' => $biaya['total_biaya_admin'],
                    'grand_total' => $qrisTotalFinal
                ];
            }

            if ($transaksi->metode_pembayaran == 'cod') {
                return response()->json([
                    "status" => 'success',
                    'messages' => "transaksi berhasil dibuat",
                    "order_id" => $transaksi->id,
                    "data" => [
                        'transaksi' => $transaksi,
                        'tenant' => $tenant,
                    ]
                ], 201);
            }

            if ($transaksi->metode_pembayaran === 'koin') {
                $saldo->jumlah -= $totalFinal;
                $saldo->save();

                TransaksiSaldoKoin::create([
                    'user_id' => $user->id,
                    'jumlah' => -$totalFinal,
                    'tipe' => 'keluar',
                    'deskripsi' => 'Pembayaran pesanan #' . $transaksi->id,
                ]);

                if (!empty($fcmTenantToken)) {
                    $this->firebases
                        ->withNotification('Pesanan Masuk', 'Ada pesanan baru masuk di tenant kamu. Yuk, segera proses!')
                        ->withData([
                            'title' => 'Pesanan Masuk',
                            'body' => 'Ada pesanan baru masuk di tenant kamu. Yuk, segera proses!',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ])->sendToTenant($fcmTenantToken);
                }

                if ($request->boolean('isPriority')) {
                    if (!empty($fcmMasbroToken)) {
                        $this->firebases
                            ->withNotification('Ada Pesanan Prioritas', 'Gasin yuk ada ongkir tambahannya loh')
                            ->withData([
                                'title' => 'Ada Pesanan Prioritas',
                                'body' => 'Gasin yuk ada ongkir tambahannya loh',
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            ])->sendToDriver($fcmMasbroToken);
                    }
                }
                Log::info('Sending FCM to tenant', ['tokens' => $fcmTenantToken]);
            }

            $transaksi = Transaksi::with(['user', 'listTransaksiDetail.menus'])->where('id', $transaksi->id)->first();

            DB::commit();

            return response()->json([
                "status" => 'success',
                'messages' => "transaksi berhasil dibuat" . ($transaksi->metode_pembayaran === 'qris' ? ', silakan lakukan pembayaran via QRIS' : ''),
                "order_id" => $transaksi->id,
                "data" => [
                    'transaksi' => array_merge(
                        $transaksi->toArray(),
                        $extraQris ?? []
                    ),
                    'tenant' => $tenant,
                ]
            ], 201);
        } else {
            DB::rollback();
            return response()->json([
                'status' => 'failed',
                'message' => 'gagal transaksi detail',
            ], 401);
        }
    }

    private function generateBiayaAdmin($nominal)
    {
        $qris = Pengaturan::where('nama', 'biaya_qris')->first();
        $layanan = Pengaturan::where('nama', 'biaya_layanan')->first();
        $qris = $qris ? (float) $qris->nilai : 0;
        $layanan = $layanan ? (int) $layanan->nilai : 0;
        $fee_midtrans = $nominal * $qris;
        $fee_midtrans = ceil($fee_midtrans);
        $total_bayar_user = $nominal + $layanan + $fee_midtrans;
        return [
            "biaya_midtrans" => $fee_midtrans,
            "biaya_ubsima" => $layanan,
            "total_biaya_admin" => $fee_midtrans + $layanan,
            "total_bayar_user" => $total_bayar_user,
        ];
    }
}
