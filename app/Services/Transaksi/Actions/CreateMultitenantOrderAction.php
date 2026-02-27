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
use App\Models\Tenants;
use App\Helper\TransaksiCek;
use App\Helpers\TransaksiHelper;
use App\Services\Firebases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateMultitenantOrderAction
{
    private $firebases;

    public function __construct(Firebases $firebases)
    {
        $this->firebases = $firebases;
    }

    public function execute(Request $request, User $user, $tenants)
    {
        $multitenantId = (Transaksi::max('multitenant_id') ?? 0) + 1;

        // Pisahkan menu berdasarkan tenant
        $menusByTenant = collect($request->menus)->groupBy(function ($menu) {
            return Menus::withTrashed()->find($menu['id'])->tenant_id;
        });

        // PRE-CALC: hitung total untuk tiap tenant dulu agar bisa cek saldo koin (grand total)
        $perTenantCalc = [];
        $grandTotal = 0;
        foreach ($menusByTenant as $tenantId => $menus) {
            $totalHargaMenu = 0;
            $totalJumlahMenu = 0;
            foreach ($menus as $menu) {
                $menuModel = Menus::withTrashed()->find($menu['id']);
                $totalHargaMenu += $menuModel->harga * $menu['jumlah'];
                $totalJumlahMenu += $menu['jumlah'];
            }

            $ruanganId = $request->isAntar ? $request->ruangan_id : null;
            $ongkosKirim = 0;
            $isPriority = filter_var($request->input('isPriority'), FILTER_VALIDATE_BOOLEAN);

            if ($request->isAntar && $ruanganId) {
                // untuk pre-calc anggap jika sudah ada transaksi sebelumnya, nanti saat pembuatan kita gunakan flag yang benar
                // namun untuk fairness, kita hitung ongkir pertama = gedung->ongkir, selanjutnya = gedung->ongkir_multitenant
                // untuk pra-calc kita anggap ordering iterasi menusByTenant sama dengan pembuatan (deterministik)
                $isSecondOrMore = count($perTenantCalc) > 0;
                $ongkosKirim = TransaksiHelper::getOngkirGedung($ruanganId, $isSecondOrMore);
                if ($isPriority && !$isSecondOrMore) {
                    $ongkirPrioritas = Pengaturan::where('nama', 'ongkos_kirim_prioritas')->value('nilai') ?? 3000;
                    $ongkosKirim += $ongkirPrioritas;
                }
            }

            $biayaLayanan = (int) (Pengaturan::where('nama', 'biaya_layanan')->value('nilai') ?? 0);
            $totalFinal = $totalHargaMenu + ($request->isAntar ? $ongkosKirim : 0) + $biayaLayanan;

            $perTenantCalc[$tenantId] = [
                'menus' => $menus,
                'totalHargaMenu' => $totalHargaMenu,
                'totalJumlahMenu' => $totalJumlahMenu,
                'ongkosKirim' => $ongkosKirim,
                'biayaLayanan' => $biayaLayanan,
                'totalFinal' => $totalFinal,
                'ruanganId' => $ruanganId,
            ];

            $grandTotal += $totalFinal;
        }

        // Hitung total seluruh item multitenant
        $totalSemuaItem = 0;
        foreach ($perTenantCalc as $calc) {
            $totalSemuaItem += $calc['totalJumlahMenu'];
        }

        // Hitung biaya extra global
        $biayaExtra = Pengaturan::where('nama', 'biaya_extra')->value('nilai') ?? 1000;

        $totalExtraGlobal = 0;
        if ($totalSemuaItem > 10) {
            $totalExtraGlobal = ($totalSemuaItem - 10) * $biayaExtra;
        }

        // Jika metode pembayaran koin -> cek saldo user mencukupi GRAND TOTAL
        if ($request->metode_pembayaran === 'koin') {
            $saldo = SaldoKoin::where('user_id', $user->id)->first();
            if (!$saldo || $saldo->jumlah < $grandTotal) {
                DB::rollBack();
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Saldo koin tidak cukup untuk pesanan multitenant',
                ], 400);
            }
        }

        $createdTransaksi = [];
        $voucherId = $request->input('voucher_id');
        $voucher = null;
        $cashback = null;
        $assignCashback = 0;
        $voucherApplied = false;

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
            $voucher = Voucher::with('cashback')->where('id', $voucherId)->where('user_id', $user->id)->first();
            $cashback = $voucher ? $voucher->cashback : null;
        }

        // Hitung cashback berdasarkan GRAND TOTAL
        $computedCashback = 0;
        $shouldApplyCashback = false;

        if ($voucher && $cashback) {
            if ($grandTotal >= ($cashback->minimal_beli ?? 0)) {
                $computedCashback = $grandTotal * $cashback->value;
                if ($cashback->max_cashback && $computedCashback > $cashback->max_cashback) {
                    $computedCashback = $cashback->max_cashback;
                }
                $shouldApplyCashback = true;
            }
        }

        $qrisCreatedYet = false;
        $firstTransaksiForQris = null;
        $qrisInfo = null;


        // Sekarang buat transaksi per tenant — gunakan nilai dari pre-calc agar konsisten
        foreach ($perTenantCalc as $tenantId => $calc) {
            $tenant = Tenants::find($tenantId); // pastikan model Tenant (singular)
            if (!$tenant)
                continue;

            $ruanganId = $calc['ruanganId'];
            $ongkosKirim = $calc['ongkosKirim'];
            $biayaLayanan = $calc['biayaLayanan'];
            $totalFinal = $calc['totalFinal'];

            if ($firstTransaksiForQris === null && $totalExtraGlobal > 0) {
                $totalFinal += $totalExtraGlobal;
                $ongkosKirim += $totalExtraGlobal;
            }

            $assignCashback = 0;
            // Apply hanya ke transaksi pertama
            if ($shouldApplyCashback && !$voucherApplied) {
                $assignCashback = $computedCashback;
            }

            // Create transaksi
            $transaksi = Transaksi::create([
                'user_id' => $user->id,
                'total' => $totalFinal,
                'isAntar' => (int) $request->boolean('isAntar'),
                'isPriority' => (int) $request->boolean('isPriority') ?? false,
                'metode_pembayaran' => $request->metode_pembayaran,
                'tenant_id' => $tenant->user_id ?? clone $tenantId, // fallback
                'ruangan_id' => $ruanganId,
                'catatan' => $request->catatan,
                'status' => $request->metode_pembayaran === 'qris'
                    ? 'pending'
                    : 'pesanan_masuk',
                'ongkos_kirim' => $ongkosKirim,
                'biaya_layanan' => $biayaLayanan,
                'catatan_lokasi_pengantaran' => $request->catatan_lokasi_pengantaran ?? null,
                'multitenant_id' => $multitenantId,
                'voucher_id' => $assignCashback ? $voucher->id : null,
                'cashback_amount' => $assignCashback ?? 0
            ]);

            if ($firstTransaksiForQris === null) {
                $firstTransaksiForQris = $transaksi;
            }

            // generate kode pemesanan unik per transaksi
            do {
                $kodePemesanan = TransaksiCek::generateKodePemesanan($transaksi->id);
            } while (Transaksi::where('kode_pemesanan', $kodePemesanan)->exists());

            $transaksi->kode_pemesanan = $kodePemesanan;
            $transaksi->save();

            // Simpan detail transaksi (kirim menus lengkap dgn catatan)
            $menusWithNotes = collect($calc['menus'])->map(function ($menu) {
                return [
                    'id' => $menu['id'],
                    'jumlah' => $menu['jumlah'],
                    'catatan' => $menu['catatan'] ?? null,
                ];
            })->toArray();

            app(\App\Http\Controllers\Transaksi\TransaksiController::class)->storeTransakasiDetail(
                new Request(['menus' => $menusWithNotes]),
                $transaksi
            );

            // Jika metode koin -> kurangi saldo per transaksi dan catat TransaksiSaldoKoin negatif
            if ($request->metode_pembayaran === 'koin') {
                // Ambil saldo fresh (atau gunakan $saldo yang sudah diambil)
                $saldo = $saldo ?? SaldoKoin::where('user_id', $user->id)->first();
                $saldo->jumlah -= $totalFinal;
                $saldo->save();

                TransaksiSaldoKoin::create([
                    'user_id' => $user->id,
                    'jumlah' => -$totalFinal,
                    'tipe' => 'keluar',
                    'deskripsi' => 'Pembayaran pesanan #' . $transaksi->id,
                ]);
            }

            // jika kita apply voucher ke transaksi ini, catat CatatVoucher & decrement quantity (HANYA SEKALI)
            if ($assignCashback > 0) {

                // Kurangi quantity baru sekali
                $cashback->decrement('quantity');
                $voucher->decrement('quantity');

                CatatVoucher::create([
                    'user_id' => $user->id,
                    'transaksi_id' => $transaksi->id,
                    'voucher_id' => $voucher->id,
                    'quantity_voucher' => 1,
                    'cashback_amount' => $assignCashback,
                ]);

                $voucherApplied = true; // pastikan tidak diaplikasikan lagi
            }

            // simpan transaksi created
            $createdTransaksi[] = $transaksi;
        }

        // === Kirim notifikasi ke tenant ===
        foreach ($createdTransaksi as $transaksiTenant) {
            $tenantUser = User::with('fcmTokens')
                ->whereHas('tenant', function ($tenant) use ($transaksiTenant) {
                    $tenant->where('user_id', $transaksiTenant->tenant_id);
                })
                ->first();

            if ($tenantUser && $tenantUser->fcmTokens->isNotEmpty()) {
                $fcmTokens = $tenantUser->fcmTokens->pluck('fcm_token')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();

                if (!empty($fcmTokens)) {
                    $this->firebases
                        ->withNotification('Pesanan Masuk', 'Ada pesanan baru masuk di tenant kamu. Yuk, segera proses!')
                        ->withData([
                            'title' => 'Pesanan Masuk',
                            'body' => 'Ada pesanan baru masuk di tenant kamu. Yuk, segera proses!',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ])
                        ->sendToTenant($fcmTokens);

                    Log::info('FCM dikirim ke tenant', [
                        'tenant_id' => $transaksiTenant->tenant_id,
                        'tokens' => $fcmTokens
                    ]);
                }
            } else {
                Log::warning('Tenant tidak punya FCM token atau user tidak ditemukan', [
                    'tenant_id' => $transaksiTenant->tenant_id
                ]);
            }
        }

        if ($request->boolean('isPriority')) {
            $masbroTokens = User::role('masbro')
                ->with('fcmTokens')
                ->get()
                ->flatMap(fn($u) => $u->fcmTokens->pluck('fcm_token'))
                ->filter()
                ->unique()
                ->values()
                ->toArray();
            if (!empty($masbroTokens)) {
                $this->firebases
                    ->withNotification('Ada Pesanan Prioritas multitenant', 'Gasin yuk ada ongkir tambahannya loh')
                    ->withData([
                        'title' => 'Ada Pesanan Prioritas',
                        'body' => 'Gasin yuk ada ongkir tambahannya loh',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ])->sendToDriver($masbroTokens);
                Log::info('Sending FCM to driver', ['tokens' => $masbroTokens]);
            }
        }

        // ==== QRIS MULTITENANT PAYMENT ====
        if ($request->metode_pembayaran === 'qris' && !$qrisCreatedYet) {

            // Tag transaksi pertama sebagai parent untuk checkout
            $firstTransaksiId = $firstTransaksiForQris->id;

            // Hitung biaya admin berdasarkan GRAND TOTAL multitenant
            $biaya = $this->generateBiayaAdmin((int) $grandTotal);

            $uuidParts = explode('-', Str::uuid()->toString());
            $shortUuid = implode('-', array_slice($uuidParts, 0, 3));

            $qrisTotalFinal = $biaya['total_biaya_admin'] + $grandTotal;

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

            // Create checkout hanya sekali (transaksi pertama sebagai induk)
            Checkout::create([
                'user_id' => $user->id,
                'transaksi_id' => $firstTransaksiId,
                'nominal' => $grandTotal,
                'biaya_midtrans' => $biaya['biaya_midtrans'],
                'biaya_ubisma' => $biaya['biaya_ubsima'],
                'total_biaya_admin' => $biaya['total_biaya_admin'],
                'total_bayar_user' => $biaya['total_bayar_user'],
                'status_bayar' => 'pending',
                'midtrans_request_id' => $orderId,
                'kode_bayar' => $snap->actions[0]->url ?? null,
                'tgl_akhir_tagihan' => $snap->expiry_time ?? null,
            ]);

            $qrisInfo = [
                'order_id_midtrans' => $orderId,
                'qr_url' => $snap->actions[0]->url ?? null,
                'expiry' => $snap->expiry_time ?? null,
                'biaya_admin' => $biaya['total_biaya_admin'],
                'grand_total' => $qrisTotalFinal
            ];

            $qrisCreatedYet = true;
        }

        DB::commit();

        return response()->json([
            'status' => 'success',
            'messages' => 'Transaksi multitenant berhasil dibuat',
            'multitenant_id' => $multitenantId,
            'data' => [
                'transaksi' => array_merge(
                    $firstTransaksiForQris->toArray(),
                    $qrisInfo ?? []
                ),
            ]
        ], 201);
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
