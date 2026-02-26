<?php

namespace App\Services\Cashier\Actions;

use App\Repositories\CashierRepository;
use App\Repositories\CashierDetailRepository;
use App\Repositories\MenuRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UpdateCashierAction
{
    protected CashierRepository $cashierRepo;
    protected CashierDetailRepository $detailRepo;
    protected MenuRepository $menuRepo;

    public function __construct(
        CashierRepository $cashierRepo,
        CashierDetailRepository $detailRepo,
        MenuRepository $menuRepo
    ) {
        $this->cashierRepo = $cashierRepo;
        $this->detailRepo = $detailRepo;
        $this->menuRepo = $menuRepo;
    }

    public function execute($id, $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'menus' => 'required|array',
            'menus.*.id' => 'required|integer|exists:menus,id',
            'menus.*.jumlah' => 'required|integer|min:1',
            'menus.*.catatan' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return [
                'error' => true,
                'status' => 400,
                'message' => $validator->errors()->all()
            ];
        }

        $cashier = $this->cashierRepo->findWithDetails($id);

        if (!$cashier) {
            return [
                'error' => true,
                'status' => 404,
                'message' => 'Transaksi kasir tidak ditemukan'
            ];
        }

        // Pastikan user pemilik tenant
        $tenant = optional(optional($cashier->details->first())->menu)->tenant;
        if (!$tenant || $tenant->user_id !== $user->id) {
            return [
                'error' => true,
                'status' => 403,
                'message' => 'Kamu bukan pemilik tenant ini, tidak bisa mengubah transaksi kasir'
            ];
        }

        DB::beginTransaction();
        try {
            $menuIds = collect($request->menus)->pluck('id')->toArray();

            // Pastikan semua menu dari tenant yang sama
            $tenantIds = $this->menuRepo->getTenantIdsByMenuIds($menuIds);
            if ($tenantIds->count() > 1) {
                return [
                    'error' => true,
                    'status' => 400,
                    'message' => 'Semua menu harus berasal dari tenant yang sama'
                ];
            }

            // Hapus detail lama
            $this->detailRepo->deleteByCashierId($cashier->id);

            $totalHarga = 0;
            $details = [];

            foreach ($request->menus as $menuItem) {
                $menu = $this->menuRepo->find($menuItem['id']);
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
            $this->detailRepo->insertMany($details);

            // Update total
            $this->cashierRepo->update($cashier, ['total' => $totalHarga]);

            DB::commit();

            return [
                'error' => false,
                'status' => 200,
                'message' => 'Transaksi kasir berhasil diperbarui',
                'data' => $cashier->load('details.menu'),
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Gagal update transaksi kasir: ' . $th->getMessage());
            return [
                'error' => true,
                'status' => 500,
                'message' => 'Terjadi kesalahan: ' . $th->getMessage()
            ];
        }
    }
}
