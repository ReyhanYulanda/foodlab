<?php

namespace App\Http\Controllers;

use App\Models\Cashier;
use App\Models\CashierDetail;
use App\Models\Menus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

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

            $cashier = Cashier::create([
                'user_id' => $user->id,
                'total' => $totalHarga,
                'kode_pemesanan' => self::generateKodePemesanan(null),
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

            return response()->json([
                'status' => 'success',
                'message' => 'Transaksi kasir berhasil dibuat',
                'data' => $cashier->load('details.menu'),
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

        // Ambil semua transaksi kasir yang menunya dimiliki oleh tenant user saat ini
        $cashiers = Cashier::whereHas('details.menu.tenant', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->with([
                'details.menu' => function ($q) {
                    $q->select('id', 'nama as nama_menu', 'harga', 'tenant_id');
                },
                'details.menu.tenant' => function ($q) {
                    $q->select('id', 'nama_tenant', 'user_id');
                },
                'user:id,name'
            ])
            ->latest()
            ->get();

        if ($cashiers->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Belum ada transaksi kasir untuk tenant ini',
                'data' => [],
            ], 200);
        }

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
                    $q->select('id', 'nama', 'harga', 'tenant_id');
                },
                'details.menu.tenant' => function ($q) {
                    $q->select('id', 'nama_tenant', 'user_id');
                },
                'user:id,name'
            ])
            ->first();

        if (!$cashier) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Transaksi kasir tidak ditemukan atau tidak memiliki akses',
            ], 404);
        }

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
