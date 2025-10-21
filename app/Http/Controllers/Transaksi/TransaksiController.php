<?php

namespace App\Http\Controllers\Transaksi;

use App\Helper\TransaksiCek;
use App\Http\Controllers\Controller;
use App\Jobs\CekMidtransTopupStatusJob;
use App\Jobs\CekTopupStatusJob;
use App\Models\Cashback;
use App\Models\CatatVoucher;
use App\Models\ChatMessage;
use App\Models\Checkout;
use App\Models\FcmToken;
use App\Models\Tenants;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use App\Models\User;
use App\Models\SaldoKoin;
use App\Models\TransaksiSaldoKoin;
use App\Models\Menus;
use App\Models\Pengaturan;
use App\Models\Ruangan;
use App\Models\TopUp;
use App\Models\Voucher;
use App\Response\ResponseApi;
use App\Services\Firebases;
use App\Services\Midtrans;
use App\Traits\CanAntar;
use Carbon\CarbonPeriod;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;
use Illuminate\Support\Str;

class TransaksiController extends Controller
{
    public function orderUser(Request $request)
    {
        $user = $request->user();

        if (!$user->can('read order user')) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        $perPage = $request->input('per_page', 10);
        $page    = $request->input('page', 1);

        $transaksi = Transaksi::with([
            'listTransaksiDetail.menus.tenants',
            'user',
            'checkout'
        ])
            ->whereHas('listTransaksiDetail.menus.tenants', function ($tenant) use ($user) {
                $tenant->where('user_id', '!=', $user->id);
            })
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        // mapping biar ada merge dari checkout
        $transaksi->getCollection()->transform(function ($item) {
            $checkout = $item->checkout;

            $item->midtrans_request_id = $checkout->midtrans_request_id ?? null;
            $item->qr_url            = $checkout->kode_bayar ?? null;
            $item->expiry            = $checkout->tgl_akhir_tagihan ?? null;
            $item->biaya_admin       = $checkout->total_biaya_admin ?? null;

            return $item;
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'data berhasil didapatkan',
            'data'    => $transaksi
        ]);
    }

    public function orderUserById(Request $request, $id)
    {
        $user = $request->user();

        if (! $user->can('read order user')) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        // Ambil transaksi yang dimiliki user (pembeli) atau tenant (pemilik toko)
        $transaksi = Transaksi::with([
            'listTransaksiDetail.menus.tenants',
            'user',
            'checkout'
        ])
            ->forUserOrTenant($user->id)
            ->where('id', $id)
            ->first();

        if (! $transaksi) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Transaksi tidak ditemukan',
            ], 404);
        }

        // Data tambahan dari tabel checkout
        $extra = [];
        if ($transaksi->checkout) {
            $extra = [
                'order_id_midtrans' => $transaksi->checkout->midtrans_request_id,
                'qr_url' => $transaksi->checkout->kode_bayar,
                'expiry' => $transaksi->checkout->tgl_akhir_tagihan,
                'biaya_admin' => $transaksi->checkout->total_biaya_admin,
            ];
        }

        return response()->json([
            'status' => 'success',
            'message' => 'data berhasil didapatkan',
            'data' => [
                'transaksi' => array_merge($transaksi->toArray(), $extra),
            ],
        ]);
    }

    public function getOnlineDriver(Request $request)
    {
        $user = $request->user();
        $permission = $user->can('read online driver');

        if (!$permission) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        $drivers = User::where('isOnline', true)
            ->whereHas('roles', function ($q) {
                $q->where('name', 'masbro');
            })
            ->get();
        $jumlahDriver = $drivers->count();

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil mengambil data driver online',
            'data' => [
                'jumlah_driver' => $jumlahDriver,
                'drivers' => $drivers
            ],
        ]);
    }

    public function orderTenant(Request $request)
    {
        $user = $request->user();

        if (!$user->can('read order tenant')) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        try {
            $tenant = Tenants::where("user_id", $user->id)->first();

            if (!$tenant) {
                return response()->json([
                    "status" => "failed",
                    "message" => "Tenant tidak ditemukan"
                ], 404);
            }

            $perPage = $request->input('per_page', 10);
            $page    = $request->input('page', 1);

            $transaksi = Transaksi::whereHas('listTransaksiDetail.menus', function ($menus) use ($tenant) {
                return $menus->where('tenant_id', $tenant->id);
            })
                ->whereNotIn('status', ['pending', 'gagal_bayar'])
                ->with([
                    'listTransaksiDetail.menus.tenants' => function ($tenants) use ($tenant) {
                        $tenants->where('id', $tenant->id);
                    },
                    'user'
                ])
                ->orderByDesc('updated_at')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                "status"  => "success",
                "message" => "Berhasil mengambil data",
                "data"    => $transaksi
            ]);
        } catch (Throwable $th) {
            Log::error($th->getMessage());
            return response()->json([
                "status"  => "server error",
                "message" => "terjadi kesalahan di server"
            ], 500);
        }
    }

    public function orderMasbro(Request $request)
    {
        $user = $request->user();

        if (!$user->can('read order masbro')) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        try {
            $perPage = $request->input('per_page', 10);
            $page    = $request->input('page', 1);

            $transaksi = Transaksi::where('isAntar', 1)
                ->where('driver_id', $user->id)
                ->whereIn('status', ['siap_diantar', 'diantar', 'selesai', 'refund_selesai', 'pesanan_masuk', 'pesanan_diproses'])
                ->with(['listTransaksiDetail.menus.tenants', 'user'])
                ->orderByDesc('created_at')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                "status"  => "success",
                "message" => "Berhasil mengambil data",
                "data"    => $transaksi
            ]);
        } catch (Throwable $th) {
            Log::error($th->getMessage());
            return response()->json([
                "status"  => "server error",
                "message" => "terjadi kesalahan di server"
            ], 500);
        }
    }

    public function store(Request $request, Firebases $firebases)
    {
        $user = $request->user();
        $permission = $user->can('create order user');
        $permission = true;

        if (!$permission) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        $validatator = Validator::make($request->all(), [
            'isAntar' => 'required|boolean',
            'ruangan_id' => 'required_if:isAntar,true',
            'metode_pembayaran' => 'required|in:koin,cod,qris',
            'catatan' => 'nullable',
            // 'status' => 'nullable',
            'menus' => 'required|array',
            'menus.*.id' => 'required|integer|exists:menus,id',
            'menus.*.jumlah' => 'required|integer|min:1',
            'catatan_lokasi_pengantaran' => 'nullable|string|max:255',
        ],);

        if ($validatator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => $validatator->errors()->all()
            ], 400);
        }

        $menu_ids = collect($request->menus)->pluck('id')->toArray();
        $tenants = Menus::whereIn('id', $menu_ids)
            ->pluck('tenant_id')
            ->unique();

        if ($tenants->count() > 1) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Semua menu harus berasal dari 1 tenant saja',
            ], 400);
        }

        $menuFirst = Menus::with('tenant.pemilik')->find($menu_ids[0]);

        if (!$menuFirst || !$menuFirst->tenant) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Tenant tidak ditemukan'
            ], 404);
        }

        $tenant = $menuFirst->tenant;

        // === ✅ Cek apakah tenant sedang online ===
        if ($tenant->isOnline == 0) {
            return response()->json([
                'status' => 'failed',
                'message' => ['Toko sedang tutup']
            ], 400);
        }

        $jumlahDriver = User::where('isOnline', true)
            ->whereHas('roles', function ($q) {
                $q->where('name', 'masbro');
            })->count();

        if ($request->isAntar && $jumlahDriver == 0) {
            return response()->json([
                'status' => 'failed',
                'message' => ['Tidak ada driver online saat ini. Silakan coba lagi nanti.']
            ], 400);
        }

        // === ✅ Cek apakah ada menu yang tidak ready ===
        $menusNotReady = Menus::withTrashed()
            ->whereIn('id', $menu_ids)
            ->where('isReady', 0)
            ->pluck('id')
            ->toArray();

        if (!empty($menusNotReady)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Beberapa menu sedang tidak tersedia',
                'data' => $menusNotReady
            ], 400);
        }

        DB::beginTransaction();
        try {
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
                $menuModel = Menus::withTrashed()->find($menu['id']);
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
                        'status'  => 'failed',
                        'message' => 'Voucher tidak valid'
                    ], 400);
                }

                $cashback = $voucher->cashback;

                if (!$cashback) {
                    return response()->json([
                        'status'  => 'failed',
                        'message' => 'Cashback tidak ditemukan'
                    ], 400);
                }

                if ($cashback->quantity <= 0) {
                    return response()->json([
                        'status'  => 'failed',
                        'message' => 'Cashback sudah habis'
                    ], 400);
                }

                if (now()->gt($cashback->end_date)) {
                    return response()->json([
                        'status'  => 'failed',
                        'message' => 'Cashback telah expired'
                    ], 400);
                }

                if ($totalFinal < $cashback->minimal_beli) {
                    return response()->json([
                        'status'  => 'failed',
                        'message' => 'Total belanja minimal ' . ($cashback->minimal_beli)
                    ], 400);
                }

                if ($voucher->user_id != $user->id) {
                    return response()->json([
                        'status'  => 'failed',
                        'message' => 'Voucher bukan milik anda'
                    ], 400);
                }

                if ($voucher->quantity <= 0) {
                    return response()->json([
                        'status'  => 'failed',
                        'message' => 'Voucher sudah habis'
                    ], 400);
                }

                if (!$cashback->is_valid) {
                    return response()->json([
                        'status'  => 'failed',
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

            $transaksi = Transaksi::create([
                'user_id' => $user->id,
                'total' => $totalFinal,
                'isAntar' => $request->isAntar,
                'isPriority' => $request->boolean('isPriority') ?? false,
                'metode_pembayaran' => $request->metode_pembayaran,
                'tenant_id' => $tenant->user_id,
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
                    'user_id'         => $user->id,
                    'transaksi_id'    => $transaksi->id,
                    'voucher_id'      => $voucher->id,
                    'quantity_voucher' => $voucher->quantity,
                    'cashback_amount' => $assignCashback,
                ]);
            }

            $success = $this->storeTransakasiDetail($request, $transaksi);

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
                    \Midtrans\Config::$isProduction = true;
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
                        $firebases
                            ->withNotification('Pesanan Masuk', 'Ada pesanan baru masuk di tenant kamu. Yuk, segera proses!')
                            ->withData([
                                'title' => 'Pesanan Masuk',
                                'body' => 'Ada pesanan baru masuk di tenant kamu. Yuk, segera proses!',
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            ])->sendToTenant($fcmTenantToken);
                    }

                    if ($request->boolean('isPriority')) {
                        if (!empty($fcmMasbroToken)) {
                            $firebases
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
        } catch (Throwable $th) {
            DB::rollback();
            Log::error('Transaksi gagal: ' . $th->getMessage());
            Log::error('Trace: ' . $th->getTraceAsString());

            return response()->json([
                'status' => 'failed',
                'messages' => 'transaksi gagal: ' . $th->getMessage(),
            ], 400);
        }
    }

    public function storeTransakasiDetail($request, $transaksi)
    {
        $validator = Validator::make($request->only(['menus']), [
            'menus' => ['required', 'array'],
            'menus.*.id' => ['required', 'numeric', 'exists:menus,id'],
            'menus.*.jumlah' => ['required', 'numeric'],
            'menus.*.catatan' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return false;
        }

        $dataInsert = [];

        foreach ($request->menus as $menu) {
            // Ambil data menu dari database berdasarkan id
            $menuModel = Menus::withTrashed()->find($menu['id']);

            if (!$menuModel) {
                // Jika menu tidak ditemukan, skip / bisa juga throw error
                continue;
            }

            $dataInsert[] = [
                'transaksi_id' => $transaksi->id,
                'menu_id' => $menu['id'],
                'jumlah' => $menu['jumlah'],
                'harga' => $menuModel->harga * $menu['jumlah'],
                'catatan' => $menu['catatan'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($dataInsert)) {
            TransaksiDetail::insert($dataInsert);
            return true;
        }

        return false;
    }

    public function webHookMidtrans(Request $request, Firebases $firebases)
    {
        $midtrans = new Midtrans();
        $notif = $midtrans->notification();

        try {
            $transaction = $notif->transaction_status;
            $order_id = $notif->order_id;

            $order_id = explode('_', $order_id);
            $order_id = $order_id[1];

            $transaksi = Transaksi::find($order_id);
            $tenant = $transaksi->listTransaksiDetail->first()->menus->tenants->pemilik;

            if ($transaction == 'settlement') {
                $transaksi->update(['status' => 'pesanan_masuk']);
                $firebases->withNotification('Pesanan Masuk', "Ada Pesanan Masuk!")
                    ->sendMessages($tenant->fcm_token);
            } else if ($transaction == 'expired') {
                $transaksi->update(['status' => $transaction]);
            } else if ($transaction == 'cancel') {
                $transaksi->update(['status' => $transaction]);
            }
        } catch (Throwable $th) {
            dd($transaksi);
        }
    }

    public function refund(Transaksi $transaksi)
    {
        try {
            $midtrans = new Midtrans();

            $refund = $midtrans->refundTransaction($transaksi);

            return ResponseApi::success(null, $refund["status_message"]);
        } catch (Exception $e) {
            return response()->json([
                "status" => "failed",
                "message" => "Refund Gagal, Silahkan Coba Lagi Nanti",
            ]);
        }
    }

    public function cancel(Request $request, $id, Firebases $firebases)
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
                return ResponseApi::success(null, "Transaksi dibatalkan dan refund berhasil");
            } catch (\Throwable $e) {
                $transaksi->status = 'refund_gagal';
                $transaksi->save();

                DB::commit(); // kita tetap commit perubahan status refund_gagal
                Log::warning("Refund gagal: " . $e->getMessage());
                return ResponseApi::error("Transaksi dibatalkan, tapi refund gagal. Silakan hubungi admin.");
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error("Gagal membatalkan transaksi: " . $th->getMessage());
            return ResponseApi::serverError();
        }
    }

    public function generateKodePemesanan(Transaksi $transaksi)
    {
        try {
            $kodePemesanan = TransaksiCek::generateKodePemesanan($transaksi->id);
            Log::info("Kode generated: " . $kodePemesanan);

            $transaksi->kode_pemesanan = $kodePemesanan;
            $transaksi->save();

            Log::info("Transaksi setelah save: ", $transaksi->toArray());
        } catch (Exception $e) {
            Log::error("Gagal membuat kode pemesanan: " . $e->getMessage());
        }
    }

    public function pushToUbisma(Request $request)
    {
        $data = $request->input('data.0');

        $validator = Validator::make($data, [
            'request_id_' => 'required|integer|digits_between:1,10',
            'nama_' => 'required|string|max:100',
            'nominal_topup_' => 'required|integer|digits_between:1,10',
            'tanggal_akhir_tagihan_' => 'required|date_format:d-m-Y H:i:s',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'code' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = [
            'procedure' => 'pfoodlab_topup',
            'data' => [$data],
        ];

        $apiKey = config('custom.mis_api_key');
        $apiUrl = config('custom.mis_api_url');

        if (is_null($apiUrl) || empty($apiUrl)) {
            return response()->json([
                'status' => 'error',
                'message' => 'MIS API URL belum diset di konfigurasi.',
            ], 500);
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'Accept' => 'application/json',
        ])->asJson()->post($apiUrl, $payload);

        return response()->json([
            'status' => $response->json('status'),
            'code' => $response->json('code'),
            'data' => $response->json('data'),
            'debug' => [
                'headers' => $response->headers(),
                'payload_sent' => $payload,
                'raw_response' => $response->json(),
                'http_status' => $response->status(),
            ]
        ], $response->status());
    }

    public function storeTopUp(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'nominal' => 'required|integer|min:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $requestId = $this->generateRequestId();
        $timeout = $this->generateTimeout();

        $dataToSend = [
            'request_id_' => $requestId,
            'nama_' => $user->name,
            'nominal_topup_' => $request->nominal,
            'tanggal_akhir_tagihan_' => $timeout->format('d-m-Y H:i:s'),
        ];

        $apiKey = config('custom.ubisma_api_key');
        $apiUrl = config('custom.ubisma_api_url');

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'Accept' => 'application/json',
        ])->asJson()->post($apiUrl, [
            'data' => [$dataToSend]
        ]);

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal terhubung ke server UBISMA.',
                'debug' => $response->body(),
            ], $response->status());
        }

        $ubismaData = $response->json('data');

        Log::info('Response dari UBISMA:', $response->json());

        if (!$ubismaData || !isset($ubismaData['kode_bayar_mandiri_'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Response UBISMA tidak valid atau tidak berisi kode bayar.',
                'debug' => $response->json()
            ], 500);
        }

        $topup = TopUp::create([
            'user_id' => $user->id,
            'request_id' => $requestId,
            'nominal' => $request->nominal,
            'kode_bayar' => $ubismaData['kode_bayar_mandiri_'] ?? null,
            'tgl_akhir_tagihan' => $timeout,
        ]);

        CekTopupStatusJob::dispatch($topup);

        return response()->json([
            'status' => 'success',
            'data' => [
                'topup' => $topup,
                'ubisma_response' => $ubismaData
            ]
        ]);
    }

    private function generateBiayaAdmin(int $nominalTopup): array
    {
        $persentaseBiayaMidtrans = 0.007; // 0,7%

        $biayaMidtrans     = (int) ceil($nominalTopup * $persentaseBiayaMidtrans);
        $totalSebelumBulat = $nominalTopup + $biayaMidtrans;
        $totalBayar        = (int) (ceil($totalSebelumBulat / 50) * 50);
        $biayaUbsima       = $totalBayar - $totalSebelumBulat;
        $totalBiayaAdmin   = $biayaMidtrans + $biayaUbsima;

        return [
            'nominal_topup'     => $nominalTopup,
            'biaya_midtrans'    => $biayaMidtrans,
            'biaya_ubsima'      => $biayaUbsima,
            'total_biaya_admin' => $totalBiayaAdmin,
            'total_bayar_user'  => $totalBayar,
            'total_sebelum_bulat' => $totalSebelumBulat
        ];
    }

    public function midtransTopUp(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'nominal' => 'required|integer|min:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $biaya = $this->generateBiayaAdmin((int) $request->nominal);

        $midtransRequestId = $this->generateMidtransRequestId();
        $dataToSend = [
            'payment_type' => 'qris',
            'transaction_details' => [
                'order_id'     => $midtransRequestId,
                'gross_amount' => $biaya['total_bayar_user'],
            ],
        ];

        $apiUrl = config('custom.midtrans_post_api_url');
        $serverKey = config('custom.midtrans_server_key');
        $authHeader = 'Basic ' . base64_encode($serverKey . ':');

        // Convert payload to JSON
        $jsonPayload = json_encode($dataToSend);

        // Init cURL
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $authHeader,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $curlError = curl_error($ch);
            curl_close($ch);
            Log::error('cURL error saat request ke Midtrans: ' . $curlError);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghubungi Midtrans.',
                'debug' => $curlError
            ], 500);
        }

        curl_close($ch);

        $midtransData = json_decode($response, true);

        if ($httpCode >= 400) {
            Log::error('Gagal request ke Midtrans', [
                'request_payload' => $dataToSend,
                'http_code' => $httpCode,
                'midtrans_response_body' => $midtransData,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal terhubung ke Midtrans.',
                'debug' => $midtransData,
            ], $httpCode);
        }

        Log::info('Response dari Midtrans:', $midtransData);

        // Ambil URL QR dari actions
        $actions = $midtransData['actions'] ?? null;
        $transactionStatus = $midtransData['transaction_status'] ?? null;

        if (!is_array($actions) || empty($actions)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data actions tidak tersedia dalam response Midtrans.',
                'debug' => $midtransData
            ], 500);
        }

        $generateQrAction = collect($actions)->firstWhere('name', 'generate-qr-code');

        if (!$generateQrAction || !isset($generateQrAction['url'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'URL QR Code tidak ditemukan dalam response Midtrans actions.',
                'debug' => $midtransData
            ], 500);
        }

        $qrCodeUrl = $generateQrAction['url'];

        // Simpan data topup
        $topup = TopUp::create([
            'user_id' => $user->id,
            'midtrans_request_id' => $midtransRequestId,
            'nominal' => $biaya['nominal_topup'],
            'biaya_midtrans' => $biaya['biaya_midtrans'],
            'biaya_ubsima' => $biaya['biaya_ubsima'],
            'total_biaya_admin' => $biaya['total_biaya_admin'],
            'total_bayar_user' => $biaya['total_bayar_user'],
            'kode_bayar' => $qrCodeUrl,
            'tgl_akhir_tagihan' => $midtransData['expiry_time'] ?? null,
            'status_bayar' => $transactionStatus,
        ]);

        CekMidtransTopupStatusJob::dispatch($topup)->delay(now()->addMinutes(5));

        return response()->json([
            'status' => 'success',
            'data' => [
                'topup' => $topup,
                'biaya_admin' => $biaya,
                'midtrans_response' => $midtransData
            ]
        ]);
    }

    public function midtransGetTopUp($midtransRequestId)
    {
        $topup = TopUp::where('midtrans_request_id', $midtransRequestId)->first();

        if (!$topup) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data topup tidak ditemukan.',
            ], 404);
        }

        if ($topup->user_id !== auth()->id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Anda tidak berhak mengakses topup ini.',
            ], 403);
        }

        // ✅ Ganti dari env() ke config()
        $apiUrl = config('custom.midtrans_get_api_url');
        $serverKey = config('custom.midtrans_server_key');
        $authHeader = 'Basic ' . base64_encode($serverKey . ':');

        // Init cURL
        $ch = curl_init($apiUrl . '/' . $midtransRequestId . '/status');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $authHeader,
            'Accept: application/json',
            // 'User-Agent: curl/7.81.0', // Sesuaikan dengan versi cURL kamu
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $curlError = curl_error($ch);
            curl_close($ch);
            Log::error('cURL error saat request ke Midtrans: ' . $curlError);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghubungi Midtrans.',
                'debug' => $curlError
            ], 500);
        }

        curl_close($ch);

        $midtransData = json_decode($response, true);

        if ($httpCode >= 400) {
            Log::error('Gagal request ke Midtrans', [
                'request_id' => $midtransRequestId,
                'http_code' => $httpCode,
                'midtrans_response_body' => $midtransData,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal terhubung ke Midtrans.',
                'debug' => $midtransData,
            ], $httpCode);
        }
        Log::info('Response dari Midtrans:', $midtransData);
        // Cek apakah data yang dibutuhkan ada
        if (!isset($midtransData['transaction_status']) || !isset($midtransData['expiry_time'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Response Midtrans tidak valid atau tidak berisi data yang dibutuhkan.',
                'debug' => $midtransData
            ], 500);
        }
        try {
            $tglBayar = Carbon::parse($midtransData['transaction_time']);
        } catch (\Exception $e) {
            Log::error('Gagal parsing transaction_time dari Midtrans: ' . json_encode($midtransData['transaction_time'] ?? null));
            $tglBayar = $topup->tgl_bayar; // fallback ke nilai sebelumnya
        }
        $topup->update([
            'status_bayar' => $midtransData['transaction_status'],
            'tgl_bayar' => $tglBayar,
        ]);
        return response()->json([
            'status' => 'success',
            'data' => $topup
        ]);
    }

    public function getTopUp($kodeBayar)
    {
        $topup = TopUp::where('kode_bayar', $kodeBayar)->first();

        if (!$topup) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data topup tidak ditemukan.',
            ], 404);
        }

        if ($topup->user_id !== auth()->id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Anda tidak berhak mengakses topup ini.',
            ], 403);
        }

        $dataToSend = [
            'request_id_' => $topup->request_id,
            'nama_' => $topup->user->name,
            'nominal_topup_' => $topup->nominal,
            'tanggal_akhir_tagihan_' => Carbon::parse($topup->tgl_akhir_tagihan)->format('d-m-Y H:i:s'),
        ];

        // ✅ Ganti dari env() ke config()
        $apiUrl = config('custom.ubisma_api_url');
        $apiKey = config('custom.ubisma_api_key');

        if (empty($apiUrl)) {
            return response()->json([
                'status' => 'error',
                'message' => 'UBISMA API URL belum dikonfigurasi.',
            ], 500);
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'Accept' => 'application/json',
        ])->asJson()->post($apiUrl, [
            'data' => [$dataToSend]
        ]);

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil status dari UBISMA.',
                'debug' => $response->body(),
            ], $response->status());
        }

        $ubismaData = $response->json('data') ?? [];

        try {
            if (!empty($ubismaData['tanggal_bayar_'])) {
                $tglBayar = Carbon::parse($ubismaData['tanggal_bayar_']);
            } else {
                $tglBayar = $topup->tgl_bayar;
            }
        } catch (\Exception $e) {
            Log::error('Gagal parsing tanggal_bayar_ dari UBISMA: ' . json_encode($ubismaData['tanggal_bayar_'] ?? null));
            $tglBayar = $topup->tgl_bayar;
        }

        $topup->update([
            'status_bayar' => $ubismaData['status_bayar_'] ?? $topup->status_bayar,
            'tgl_bayar' => $tglBayar,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $topup
        ]);
    }

    protected function generateMidtransRequestId()
    {
        $starting = config('custom.midtrans_request_id_start');
        Log::info("Starting MIDTRANS_REQUEST_ID_START: " . $starting);

        if (is_null($starting)) {
            throw new \Exception("MIDTRANS_REQUEST_ID_START belum diset di environment");
        }

        $lastNumber = TopUp::whereNotNull('midtrans_request_id')
            ->where('midtrans_request_id', 'like', 'foodlab-%')
            ->selectRaw("MAX(CAST(SUBSTRING_INDEX(midtrans_request_id, '-', -1) AS UNSIGNED)) as max_id")
            ->value('max_id');

        $next = ($lastNumber && $lastNumber >= $starting) ? $lastNumber + 1 : $starting;

        return 'foodlab-' . $next;
    }

    // Start dari 102 dan terus naik
    protected function generateRequestId()
    {
        $starting = config('custom.request_id_start');

        if (is_null($starting)) {
            throw new \Exception("REQUEST_ID_START belum diset di environment");
        }

        $last = TopUp::max('request_id');

        return ($last && $last >= $starting) ? $last + 1 : $starting;
    }

    protected function generateTimeout()
    {
        return Carbon::now()->addHour();
    }

    public function sendMessage($transaksiId, Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'chat_type' => 'required|in:tenant,driver',
            // 'sender_name' => 'required|string|max:100',
        ]);

        $transaksi = Transaksi::findOrFail($transaksiId);

        // Kalau transaksi sudah di-soft delete, hentikan
        if ($transaksi->trashed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sesi chat telah berakhir.'
            ], 400);
        }

        if (!in_array(Auth::id(), [$transaksi->user_id, $transaksi->tenant_id, $transaksi->driver_id])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki akses untuk mengirim chat pada transaksi ini.'
            ], 403);
        }


        // Simpan pesan
        $chat = ChatMessage::create([
            'transaksi_id' => $transaksiId,
            'sender_id' => Auth::id(),
            'message' => $request->input('message'),
            'chat_type' => $request->input('chat_type'), // tambahkan ini
            'sender_name' => Auth::user()->name, // gunakan nama user yang sedang login
        ]);


        // Tentukan penerima berdasarkan role
        $receiverIds = [];

        if (Auth::id() === $transaksi->tenant_id) {
            // Tenant kirim → Buyer
            $receiverIds[] = $transaksi->user_id;
        } elseif (Auth::id() === $transaksi->driver_id) {
            // Driver kirim → Buyer
            $receiverIds[] = $transaksi->user_id;
        } elseif (Auth::id() === $transaksi->user_id) {
            // Buyer kirim → Tenant & Driver
            // NOTE: buyer hanya kirim ke salah satu sesuai context chat room
            if ($request->input('chat_type') === 'tenant') {
                $receiverIds[] = $transaksi->tenant_id;
            } elseif ($request->input('chat_type') === 'driver') {
                $receiverIds[] = $transaksi->driver_id;
            }
        }

        // Ambil token FCM penerima
        $receiverTokens = \App\Models\User::whereIn('id', array_filter($receiverIds))
            ->with('fcmTokens')
            ->get()
            ->pluck('fcmTokens.*.fcm_token')
            ->flatten()
            ->filter()
            ->unique()
            ->toArray();

        // Kirim FCM
        if (!empty($receiverTokens)) {
            $firebases = new Firebases();
            $firebases->withNotification('Chat Baru Order - ' . $transaksiId, $request->input('message'))
                ->withData([
                    'title' => 'Chat Baru Order - ' . $transaksiId,
                    'body' => $request->input('message'),
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'transaksi_id' => $transaksiId
                ])
                ->sendToFallback($receiverTokens);
        }

        Log::info('Pesan baru dikirim', [
            'transaksi_id' => $transaksiId,
            'sender_id' => Auth::id(),
            'message' => $request->input('message'),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $chat
        ]);
    }

    public function getMessageDriverToBuyer($transaksiId)
    {
        $transaksi = Transaksi::findOrFail($transaksiId);
        if (!in_array(Auth::id(), [$transaksi->user_id, $transaksi->tenant_id, $transaksi->driver_id])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki akses untuk melihat chat pada transaksi ini.'
            ], 403);
        }

        if ($transaksi->trashed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sesi chat telah berakhir.'
            ], 400);
        }

        return ChatMessage::where('transaksi_id', $transaksiId)
            ->where('chat_type', 'driver')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function getMessageTenantToBuyer($transaksiId)
    {
        $transaksi = Transaksi::findOrFail($transaksiId);
        if (!in_array(Auth::id(), [$transaksi->user_id, $transaksi->tenant_id, $transaksi->driver_id])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki akses untuk melihat chat pada transaksi ini.'
            ], 403);
        }

        if ($transaksi->trashed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sesi chat telah berakhir.'
            ], 400);
        }

        return ChatMessage::where('transaksi_id', $transaksiId)
            ->where('chat_type', 'tenant')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function getLeaderboardDriver()
    {
        $leaderboard = Transaksi::where('status', 'selesai')
            ->whereNotNull('driver_id')
            ->select('driver_id', DB::raw('COUNT(*) as total_transaksi'))
            ->groupBy('driver_id')
            ->orderByDesc('total_transaksi')
            ->get(); // pastikan get() dulu

        $leaderboard = $leaderboard->map(function ($item) {
            $driver = $item->driver()->first(); // akses relasi driver manual
            return [
                'nama_driver'     => $driver ? $driver->name : null,
                'foto_driver'     => $driver ? $driver->image : null,
                'total_transaksi' => $item->total_transaksi,
            ];
        });

        return response()->json([
            'status' => true,
            'data'   => $leaderboard,
        ]);
    }

    public function pushNotificationDriverToBuyer(Request $request, $transaksiId)
    {
        try {
            $user = $request->user();
            $permission = $user->can('create ping driver to buyer');

            if (!$permission) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'tidak memiliki akses',
                ], 403);
            }

            $transaksi = Transaksi::findOrFail($transaksiId);

            if (!$transaksi->user_id) {
                return response()->json(['message' => 'Transaksi tidak memiliki user'], 404);
            }

            if (Auth::id() != $transaksi->driver_id) {
                return response()->json(['message' => 'Anda tidak memiliki akses pada transaksi ini.'], 403);
            }

            if ($transaksi->status == 'selesai') {
                return response()->json(['message' => 'Transaksi sudah selesai'], 404);
            }

            $cacheKey = "driver_ping:" . Auth::id() . ":transaksi:" . $transaksi->id;
            if (Cache::has($cacheKey)) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Anda hanya bisa mengirim ping setiap 20 detik sekali.'
                ], 429);
            }
            // Simpan ke cache dengan TTL 20 detik
            Cache::put($cacheKey, true, now()->addSeconds(20));

            $userId = $transaksi->user_id;

            $tokens = FcmToken::where('user_id', $userId)->pluck('fcm_token')->toArray();

            if (empty($tokens)) {
                return response()->json(['message' => 'User tidak memiliki FCM token'], 404);
            }

            $title = "📢 Driver menghubungi anda";
            $body  = "Driver bisa saja mengirim pesan atau memberi tahu bahwa ia sudah tiba di lokasi.";

            $firebase = new Firebases();
            $firebase->withNotification($title, $body)
                ->withNotification($title, $body)
                ->withData([
                    'title' => $title,
                    'body' => $body,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ])
                ->sendToDriver($tokens);

            return response()->json([
                'message' => 'Notifikasi berhasil dikirim ke pembeli',
                'pembeli' => $userId,
                'driver' => Auth::id()
            ]);
        } catch (\Throwable $e) {
            Log::error("pushNotificationDriverToBuyer Error: " . $e->getMessage());
            return response()->json([
                'message' => 'Gagal mengirim notifikasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function handleCallback(Request $request)
    {
        $serverKey = config('custom.midtrans_server_key');
        $json = $request->all();

        Log::info('Webhook Callback dari Midtrans:', $json);

        // Validasi Signature
        $orderId      = $json['order_id'] ?? null;
        $statusCode   = $json['status_code'] ?? null;
        $grossAmount  = $json['gross_amount'] ?? null;
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

        // 🔎 Step 1: Cek dulu di Checkout (pesanan QRIS)
        $checkout = Checkout::where('midtrans_request_id', $orderId)->first();

        if ($checkout) {
            DB::transaction(function () use ($checkout, $transactionStatus, $json) {
                if (in_array($transactionStatus, ['capture', 'settlement'])) {
                    $checkout->update([
                        'status_bayar' => 'settlement',
                        'tgl_bayar' => $json['settlement_time'] ?? now()
                    ]);

                    // Update Transaksi → pesanan_masuk
                    $transaksi = Transaksi::find($checkout->transaksi_id);
                    if ($transaksi && $transaksi->status === 'pending') {
                        $transaksi->status = 'pesanan_masuk';
                        $transaksi->save();

                        // 🚀 Notifikasi ke tenant
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
                } elseif ($transactionStatus === 'pending') {
                    $checkout->update(['status_bayar' => 'pending']);
                } elseif (in_array($transactionStatus, ['deny', 'cancel', 'expire'])) {
                    $checkout->update(['status_bayar' => 'failed']);
                } else {
                    $checkout->update(['status_bayar' => 'unknown']);
                }
            });
            return response()->json(['message' => 'OK Checkout'], 200);
        }

        // 🔎 Step 2: Kalau bukan Checkout, cek di TopUp
        $transaction = TopUp::where('midtrans_request_id', $orderId)->first();
        if (!$transaction) {
            Log::error('Transaksi tidak ditemukan untuk order_id: ' . $orderId);
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        // === Logic TopUp (punya kamu sebelumnya, gak diubah) ===
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

    public function getPenghasilanTenant(Request $request)
    {
        $tenantId = $request->user()->id; // ambil id tenant dari user login
        $year     = $request->query('year');
        $month    = $request->query('month');
        $date     = $request->query('date');

        $labels        = [];
        $selesaiData   = [];
        $refundData    = [];
        $transaksiList = [];

        // default: all time
        $dateStart = null;
        $dateEnd   = null;

        if ($year && !$month && !$date) {
            // mode yearly → data per bulan
            for ($m = 1; $m <= 12; $m++) {
                $start = Carbon::create($year, $m, 1)->startOfMonth();
                $end   = Carbon::create($year, $m, 1)->endOfMonth();

                $labels[] = $start->locale('id')->translatedFormat('F');

                $selesaiData[] = Transaksi::whereHas('listTransaksiDetail.menus.tenants', function ($q) use ($tenantId) {
                    $q->where('user_id', $tenantId);
                })
                    ->where('status', 'selesai')
                    ->whereBetween('updated_at', [$start, $end])
                    ->count();

                $refundData[] = Transaksi::whereHas('listTransaksiDetail.menus.tenants', function ($q) use ($tenantId) {
                    $q->where('user_id', $tenantId);
                })
                    ->where('status', 'refund_selesai')
                    ->whereBetween('updated_at', [$start, $end])
                    ->count();
            }

            $dateStart = Carbon::create($year, 1, 1)->startOfYear();
            $dateEnd   = Carbon::create($year, 12, 31)->endOfYear();
        } elseif ($year && $month && !$date) {
            // mode monthly → data per minggu (maks 5 minggu)
            $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
            $endOfMonth   = Carbon::create($year, $month, 1)->endOfMonth();

            // 1) Bentuk minggu mentah (Senin–Minggu), lalu clamp ke dalam bulan
            $period = CarbonPeriod::create(
                $startOfMonth->copy()->startOfWeek(Carbon::MONDAY),
                '1 week',
                $endOfMonth->copy()->endOfWeek(Carbon::SUNDAY)
            );

            $rawWeeks = [];
            foreach ($period as $weekStart) {
                $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

                // Clamp ke bulan
                if ($weekStart < $startOfMonth) $weekStart = $startOfMonth->copy();
                if ($weekEnd   > $endOfMonth)   $weekEnd   = $endOfMonth->copy();

                // Abaikan jika sudah invalid setelah clamp
                if ($weekStart > $weekEnd) continue;

                $rawWeeks[] = [$weekStart, $weekEnd];
            }

            // 2) Normalisasi ke maksimal 5 minggu:
            //    Jika dapat 6 minggu, gabungkan minggu dengan durasi paling kecil ke tetangganya.
            if (count($rawWeeks) > 5) {
                // Hitung durasi (hari) tiap minggu
                $durations = array_map(function ($range) {
                    /** @var Carbon $a; @var Carbon $b */
                    [$a, $b] = $range;
                    return $a->diffInDays($b) + 1; // inklusif
                }, $rawWeeks);

                $minIdx = array_keys($durations, min($durations))[0];

                // Prefer merge minggu parsial awal ke minggu berikutnya,
                // kalau parsialnya di akhir, merge ke sebelumnya.
                if ($minIdx === 0 && isset($rawWeeks[1])) {
                    // Gabungkan awal → minggu ke-2
                    $rawWeeks[1][0] = $rawWeeks[0][0]->copy();
                    array_splice($rawWeeks, 0, 1);
                } elseif ($minIdx === count($rawWeeks) - 1 && isset($rawWeeks[$minIdx - 1])) {
                    // Gabungkan akhir → minggu sebelumnya
                    $rawWeeks[$minIdx - 1][1] = $rawWeeks[$minIdx][1]->copy();
                    array_splice($rawWeeks, $minIdx, 1);
                } else {
                    // Parsial di tengah: pilih tetangga dengan durasi lebih kecil agar gabungan tetap seimbang
                    $leftDur  = $durations[$minIdx - 1] ?? PHP_INT_MAX;
                    $rightDur = $durations[$minIdx + 1] ?? PHP_INT_MAX;

                    if ($rightDur <= $leftDur && isset($rawWeeks[$minIdx + 1])) {
                        // merge ke kanan
                        $rawWeeks[$minIdx + 1][0] = $rawWeeks[$minIdx][0]->copy();
                        array_splice($rawWeeks, $minIdx, 1);
                    } else {
                        // merge ke kiri
                        $rawWeeks[$minIdx - 1][1] = $rawWeeks[$minIdx][1]->copy();
                        array_splice($rawWeeks, $minIdx, 1);
                    }
                }
            }

            // 3) Pakai $rawWeeks (sudah <= 5) sebagai $weekRanges final
            $weekRanges = [];
            $labels = [];
            $selesaiData = [];
            $refundData = [];

            $week = 1;
            foreach ($rawWeeks as [$weekStart, $weekEnd]) {
                $label = "Minggu {$week}";
                $labels[] = $label;
                $weekRanges[$label] = [$weekStart, $weekEnd];

                $selesaiData[] = Transaksi::whereHas('listTransaksiDetail.menus.tenants', function ($q) use ($tenantId) {
                    $q->where('user_id', $tenantId);
                })
                    ->where('status', 'selesai')
                    ->whereBetween('updated_at', [$weekStart, $weekEnd])
                    ->count();

                $refundData[] = Transaksi::whereHas('listTransaksiDetail.menus.tenants', function ($q) use ($tenantId) {
                    $q->where('user_id', $tenantId);
                })
                    ->where('status', 'refund_selesai')
                    ->whereBetween('updated_at', [$weekStart, $weekEnd])
                    ->count();

                $week++;
            }

            $dateStart = $startOfMonth;
            $dateEnd   = $endOfMonth;
        } elseif ($year && $month && $date) {
            // mode daily → ambil minggu dari tanggal yang dipilih
            $filterDate = Carbon::create($year, $month, $date);

            // tentukan rentang minggu (Senin - Minggu) berdasarkan tanggal itu
            $startOfWeek = $filterDate->copy()->startOfWeek(Carbon::MONDAY);
            $endOfWeek   = $filterDate->copy()->endOfWeek(Carbon::SUNDAY);

            $dateStart = $startOfWeek;
            $dateEnd   = $endOfWeek;

            // looping setiap hari dalam minggu itu
            $period = CarbonPeriod::create($startOfWeek, $endOfWeek);

            foreach ($period as $day) {
                $dayStart = $day->copy()->startOfDay();
                $dayEnd   = $day->copy()->endOfDay();

                $labels[] = $day->locale('id')->translatedFormat('l'); // Senin, Selasa, dst (bahasa Indonesia)

                $selesaiData[] = Transaksi::whereHas('listTransaksiDetail.menus.tenants', function ($q) use ($tenantId) {
                    $q->where('user_id', $tenantId);
                })
                    ->where('status', 'selesai')
                    ->whereBetween('updated_at', [$dayStart, $dayEnd])
                    ->count();

                $refundData[] = Transaksi::whereHas('listTransaksiDetail.menus.tenants', function ($q) use ($tenantId) {
                    $q->where('user_id', $tenantId);
                })
                    ->where('status', 'refund_selesai')
                    ->whereBetween('updated_at', [$dayStart, $dayEnd])
                    ->count();
            }
        } else {
            // mode all time
            $labels[] = 'All Time';

            $selesaiData[] = Transaksi::whereHas('listTransaksiDetail.menus.tenants', function ($q) use ($tenantId) {
                $q->where('user_id', $tenantId);
            })
                ->where('status', 'selesai')
                ->count();

            $refundData[] = Transaksi::whereHas('listTransaksiDetail.menus.tenants', function ($q) use ($tenantId) {
                $q->where('user_id', $tenantId);
            })
                ->where('status', 'refund_selesai')
                ->count();
        }

        // transaksi detail (list)
        $transaksiQuery = Transaksi::whereHas('listTransaksiDetail.menus.tenants', function ($q) use ($tenantId) {
            $q->where('user_id', $tenantId);
        })->when($dateStart && $dateEnd, function ($q) use ($dateStart, $dateEnd) {
            $q->whereBetween('updated_at', [$dateStart, $dateEnd]);
        })
            ->orderBy('updated_at', 'asc')
            ->get();

        foreach ($transaksiQuery as $trx) {
            $harga = max(0, (int)$trx->total - (int)($trx->ongkos_kirim ?? 0));
            $bersih = $trx->status === 'selesai' ? $harga - (0.1 * $harga) : 0;

            // default null
            $labelTrx = null;

            if (!empty($weekRanges)) {
                // mode monthly → cari minggu transaksi
                foreach ($weekRanges as $label => [$start, $end]) {
                    if ($trx->updated_at->between($start, $end)) {
                        $labelTrx = $label;
                        break;
                    }
                }
            } elseif ($year && $month && $date) {
                // mode daily → pakai nama hari
                $labelTrx = $trx->updated_at->locale('id')->translatedFormat('l');
            } else {
                // fallback (yearly / all time) → bisa pakai bulan atau null
                $labelTrx = $trx->updated_at->locale('id')->translatedFormat('F');
            }

            $transaksiList[] = [
                'id'                => $trx->id,
                'status'            => $trx->status,
                'harga'             => $harga,
                'pendapatan_bersih' => $bersih,
                'tanggal'           => $trx->updated_at->format('d-m-Y H:i:s'),
                'label'             => $labelTrx,
            ];
        }

        // total pendapatan bersih
        $totalPendapatan = 0;
        $transaksiSelesai = Transaksi::whereHas('listTransaksiDetail.menus.tenants', function ($q) use ($tenantId) {
            $q->where('user_id', $tenantId);
        })
            ->where('status', 'selesai')
            ->when($dateStart && $dateEnd, function ($q) use ($dateStart, $dateEnd) {
                $q->whereBetween('updated_at', [$dateStart, $dateEnd]);
            })
            ->get();

        foreach ($transaksiSelesai as $trx) {
            $harga = ($trx->total ?? 0) - ($trx->ongkos_kirim ?? 0);
            $totalPendapatan += $harga - (0.1 * $harga);
        }

        return response()->json([
            'labels'            => $labels,
            'selesaiData'       => array_map('intval', $selesaiData),
            'refundData'        => array_map('intval', $refundData),
            'totalSelesai'      => intval(array_sum($selesaiData)),
            'totalRefund'       => intval(array_sum($refundData)),
            'totalPendapatan'   => intval($totalPendapatan),
            'transaksi' => collect($transaksiList)->map(function ($trx) {
                return [
                    'id'                => intval($trx['id']),
                    'status'            => $trx['status'],
                    'harga'             => intval($trx['harga']),
                    'pendapatan_bersih' => intval($trx['pendapatan_bersih']),
                    'tanggal'           => $trx['tanggal'],
                    'label'             => $trx['label'],
                ];
            }),
        ]);
    }
}
