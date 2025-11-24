<?php

namespace App\Http\Controllers;

use App\Models\Cashier;
use App\Models\CashierDetail;
use App\Models\Checkout;
use App\Models\Menus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CashierController extends Controller
{

    public static function generateKodePemesanan($cashierId)
    {
        // Generate 3 huruf kapital acak
        $huruf = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 3));

        // Generate 2 angka acak (00–99)
        $angka = str_pad(random_int(0, 99), 2, '0', STR_PAD_LEFT);

        // Gabungkan
        return $huruf . $angka;
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'menus' => 'required|array',
            'menus.*.id' => 'required|integer|exists:menus,id',
            'menus.*.jumlah' => 'required|integer|min:1',
            'menus.*.catatan' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => $validator->errors()->all()
            ], 400);
        }

        $menuIds = collect($request->menus)->pluck('id')->toArray();

        // Ambil semua tenant dari menu yang dipilih
        $tenants = Menus::whereIn('id', $menuIds)
            ->pluck('tenant_id')
            ->unique();

        // Pastikan semua menu dari 1 tenant
        if ($tenants->count() > 1) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Semua menu harus berasal dari tenant yang sama',
            ], 400);
        }

        $menuFirst = Menus::with('tenant.pemilik')->find($menuIds[0]);
        if (!$menuFirst || !$menuFirst->tenant) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Tenant tidak ditemukan',
            ], 404);
        }

        $tenant = $menuFirst->tenant;

        // ✅ Cek apakah user yang login adalah pemilik tenant
        if ($tenant->user_id !== $user->id) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Kamu bukan pemilik tenant ini, tidak bisa menambahkan transaksi kasir',
            ], 403);
        }

        DB::beginTransaction();
        try {
            $totalHarga = 0;
            foreach ($request->menus as $menuItem) {
                $menu = Menus::find($menuItem['id']);
                if ($menu) {
                    $totalHarga += $menu->harga * $menuItem['jumlah'];
                }
            }

            // ✅ Cari order_tenant terakhir milik tenant ini
            $lastOrderTenant = Cashier::where('tenant_id', $tenant->id)
                ->max('order_tenant');

            $nextOrderTenant = $lastOrderTenant ? $lastOrderTenant + 1 : 1;

            $cashier = Cashier::create([
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'order_tenant' => $nextOrderTenant,
                'total' => $totalHarga,
                'kode_pemesanan' => self::generateKodePemesanan(null),
                'status' => 'pending', // ✅ default status
            ]);

            $details = [];
            foreach ($request->menus as $menuItem) {
                $menu = Menus::find($menuItem['id']);
                if ($menu) {
                    $details[] = [
                        'cashier_id' => $cashier->id,
                        'menu_id' => $menu->id,
                        'jumlah' => $menuItem['jumlah'],
                        'harga' => $menu->harga * $menuItem['jumlah'],
                        'catatan' => $menuItem['catatan'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (!empty($details)) {
                CashierDetail::insert($details);
            }

            DB::commit();

            $uuidParts = explode('-', Str::uuid()->toString());
            $shortUuid = implode('-', array_slice($uuidParts, 0, 3));
            $qrisTotalFinal = $totalHarga;

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
                // 'transaksi_id' => $transaksi->id,
                'cashier_id' => $cashier->id,
                'nominal' => $totalHarga,
                'biaya_midtrans' => 0,
                'biaya_ubisma' => 0,
                'total_biaya_admin' => 0,
                'total_bayar_user' => $totalHarga,
                'status_bayar' => 'pending',
                'midtrans_request_id' => $orderId,
                'kode_bayar' => $snap->actions[0]->url ?? null,
                'tgl_akhir_tagihan' => $snap->expiry_time ?? null,
            ]);

            $extraQris = [
                'order_id_midtrans' => $orderId,
                'qr_url' => $snap->actions[0]->url ?? null,
                'expiry' => $snap->expiry_time ?? null,
                'biaya_admin' => 0,
            ];

            return response()->json([
                'status' => 'success',
                'message' => 'Transaksi kasir berhasil dibuat',
                'data' => array_merge(
                    $cashier->load('details.menu')->toArray(),
                    $extraQris
                ),
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Gagal membuat transaksi kasir: ' . $th->getMessage());
            return response()->json([
                'status' => 'failed',
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function getHistory(Request $request)
    {
        $user = $request->user();

        $cashiers = Cashier::whereHas('details.menu.tenant', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->whereNotNull('order_tenant')
            ->with([
                'details.menu' => function ($q) {
                    $q->select('id', 'nama as nama_menu', 'harga', 'tenant_id', 'gambar');
                },
                'details.menu.tenant' => function ($q) {
                    $q->select('id', 'nama_tenant', 'user_id', 'nama_gambar');
                },
                'user:id,name',
                'checkout'
            ])
            ->orderBy('order_tenant', 'asc')
            ->get();

        if ($cashiers->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Belum ada transaksi kasir untuk tenant ini',
                'data' => [],
            ], 200);
        }

        // Tambahkan extra field ke setiap cashier
        $cashiers = $cashiers->map(function ($c) {

            $extra = [];

            if ($c->checkout) {
                $extra = [
                    'order_id_midtrans' => $c->checkout->midtrans_request_id,
                    'qr_url'            => $c->checkout->kode_bayar,
                    'expiry'            => $c->checkout->tgl_akhir_tagihan,
                ];
            }

            // merge extra ke structure cashier (tidak mengubah struktur)
            return array_merge($c->toArray(), $extra);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil mengambil riwayat kasir',
            'data' => $cashiers,
        ], 200);
    }

    public function getHistoryById(Request $request, $id)
    {
        $user = $request->user();

        // Ambil data kasir berdasarkan ID
        $cashier = Cashier::where('id', $id)
            ->whereHas('details.menu.tenant', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with([
                'details.menu' => function ($q) {
                    $q->select('id', 'nama as nama_menu', 'harga', 'tenant_id');
                },
                'details.menu.tenant' => function ($q) {
                    $q->select('id', 'nama_tenant', 'user_id');
                },
                'user:id,name',
                'checkout' // tambahkan relasi checkout
            ])
            ->first();

        if (!$cashier) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Transaksi kasir tidak ditemukan atau tidak memiliki akses',
            ], 404);
        }

        // ────────────────────────────────────────
        // Tambahkan EXTRA field dari checkout
        // ────────────────────────────────────────
        $extra = [];

        if ($cashier->checkout) {
            $extra = [
                'order_id_midtrans' => $cashier->checkout->midtrans_request_id,
                'qr_url'            => $cashier->checkout->kode_bayar,
                'expiry'            => $cashier->checkout->tgl_akhir_tagihan,
            ];
        }

        // Merge extra ke root data cashier (tanpa ubah struktur)
        $cashier = array_merge($cashier->toArray(), $extra);

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil mengambil detail transaksi kasir',
            'data' => $cashier,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'menus' => 'required|array',
            'menus.*.id' => 'required|integer|exists:menus,id',
            'menus.*.jumlah' => 'required|integer|min:1',
            'menus.*.catatan' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => $validator->errors()->all()
            ], 400);
        }

        $cashier = Cashier::with('details.menu.tenant')->find($id);

        if (!$cashier) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Transaksi kasir tidak ditemukan',
            ], 404);
        }

        // Pastikan user pemilik tenant
        $tenant = optional(optional($cashier->details->first())->menu)->tenant;
        if (!$tenant || $tenant->user_id !== $user->id) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Kamu bukan pemilik tenant ini, tidak bisa mengubah transaksi kasir',
            ], 403);
        }

        DB::beginTransaction();
        try {
            $menuIds = collect($request->menus)->pluck('id')->toArray();

            // Pastikan semua menu dari tenant yang sama
            $tenantIds = Menus::whereIn('id', $menuIds)->pluck('tenant_id')->unique();
            if ($tenantIds->count() > 1) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Semua menu harus berasal dari tenant yang sama',
                ], 400);
            }

            // Hapus detail lama
            CashierDetail::where('cashier_id', $cashier->id)->delete();

            $totalHarga = 0;
            $details = [];

            foreach ($request->menus as $menuItem) {
                $menu = Menus::find($menuItem['id']);
                if ($menu) {
                    $subtotal = $menu->harga * $menuItem['jumlah'];
                    $totalHarga += $subtotal;

                    $details[] = [
                        'cashier_id' => $cashier->id,
                        'menu_id' => $menu->id,
                        'jumlah' => $menuItem['jumlah'],
                        'harga' => $subtotal,
                        'catatan' => $menuItem['catatan'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Insert detail baru
            if (!empty($details)) {
                CashierDetail::insert($details);
            }

            // Update total
            $cashier->update([
                'total' => $totalHarga,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Transaksi kasir berhasil diperbarui',
                'data' => $cashier->load('details.menu'),
            ], 200);
        } catch (Throwable $th) {
            DB::rollBack();
            Log::error('Gagal update transaksi kasir: ' . $th->getMessage());
            return response()->json([
                'status' => 'failed',
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
            ], 500);
        }
    }


    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $cashier = Cashier::with('details.menu.tenant')->find($id);

        if (!$cashier) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Transaksi kasir tidak ditemukan',
            ], 404);
        }

        // Pastikan user pemilik tenant
        $tenant = optional(optional($cashier->details->first())->menu)->tenant;
        if (!$tenant || $tenant->user_id !== $user->id) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Kamu bukan pemilik tenant ini, tidak bisa menghapus transaksi kasir',
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Hapus semua detail
            CashierDetail::where('cashier_id', $cashier->id)->delete();
            $cashier->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Transaksi kasir berhasil dihapus',
            ], 200);
        } catch (Throwable $th) {
            DB::rollBack();
            Log::error('Gagal menghapus transaksi kasir: ' . $th->getMessage());
            return response()->json([
                'status' => 'failed',
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
            ], 500);
        }
    }
}
