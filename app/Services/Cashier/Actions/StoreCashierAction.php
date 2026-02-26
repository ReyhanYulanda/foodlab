<?php

namespace App\Services\Cashier\Actions;

use App\Repositories\CashierRepository;
use App\Repositories\CashierDetailRepository;
use App\Repositories\CheckoutRepository;
use App\Repositories\MenuRepository;
use App\Services\Cashier\Helpers\KodePemesananGenerator;
use App\Services\Cashier\Helpers\OrderTenantGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class StoreCashierAction
{
    protected CashierRepository $cashierRepo;
    protected CashierDetailRepository $detailRepo;
    protected CheckoutRepository $checkoutRepo;
    protected MenuRepository $menuRepo;
    protected OrderTenantGenerator $orderGenerator;

    public function __construct(
        CashierRepository $cashierRepo,
        CashierDetailRepository $detailRepo,
        CheckoutRepository $checkoutRepo,
        MenuRepository $menuRepo,
        OrderTenantGenerator $orderGenerator
    ) {
        $this->cashierRepo = $cashierRepo;
        $this->detailRepo = $detailRepo;
        $this->checkoutRepo = $checkoutRepo;
        $this->menuRepo = $menuRepo;
        $this->orderGenerator = $orderGenerator;
    }

    public function execute($request)
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

        $menuIds = collect($request->menus)->pluck('id')->toArray();

        // Ambil semua tenant dari menu yang dipilih
        $tenants = $this->menuRepo->getTenantIdsByMenuIds($menuIds);

        // Pastikan semua menu dari 1 tenant
        if ($tenants->count() > 1) {
            return [
                'error' => true,
                'status' => 400,
                'message' => 'Semua menu harus berasal dari tenant yang sama'
            ];
        }

        $menuFirst = $this->menuRepo->findWithTenant($menuIds[0]);
        if (!$menuFirst || !$menuFirst->tenant) {
            return [
                'error' => true,
                'status' => 404,
                'message' => 'Tenant tidak ditemukan'
            ];
        }

        $tenant = $menuFirst->tenant;

        // Cek apakah user yang login adalah pemilik tenant
        if ($tenant->user_id !== $user->id) {
            return [
                'error' => true,
                'status' => 403,
                'message' => 'Kamu bukan pemilik tenant ini, tidak bisa menambahkan transaksi kasir'
            ];
        }

        DB::beginTransaction();
        try {
            $totalHarga = 0;
            foreach ($request->menus as $menuItem) {
                $menu = $this->menuRepo->find($menuItem['id']);
                if ($menu) {
                    $totalHarga += $menu->harga * $menuItem['jumlah'];
                }
            }

            $nextOrderTenant = $this->orderGenerator->generate($tenant->id);

            $cashier = $this->cashierRepo->create([
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'order_tenant' => $nextOrderTenant,
                'total' => $totalHarga,
                'kode_pemesanan' => KodePemesananGenerator::generate(),
                'status' => 'pending',
            ]);

            $details = [];
            foreach ($request->menus as $menuItem) {
                $menu = $this->menuRepo->find($menuItem['id']);
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

            $this->detailRepo->insertMany($details);

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

            $this->checkoutRepo->create([
                'user_id' => $user->id,
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

            return [
                'error' => false,
                'status' => 201,
                'message' => 'Transaksi kasir berhasil dibuat',
                'data' => array_merge(
                    $cashier->load('details.menu')->toArray(),
                    $extraQris
                ),
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Gagal membuat transaksi kasir: ' . $th->getMessage());
            return [
                'error' => true,
                'status' => 500,
                'message' => 'Terjadi kesalahan: ' . $th->getMessage()
            ];
        }
    }
}
