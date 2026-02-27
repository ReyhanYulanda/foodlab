<?php

namespace App\Http\Controllers\Transaksi;

use App\Helper\TransaksiCek;
use App\Http\Controllers\Controller;
use App\Jobs\CekMidtransTopupStatusJob;
use App\Jobs\CekTopupStatusJob;
use App\Models\Cashback;
use App\Models\Cashier;
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
    public function orderUser(Request $request, \App\Services\Transaksi\Actions\GetOrderUserAction $action)
    {
        $user = $request->user();

        if (!$user->can('read order user')) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        $transaksi = $action->execute($request, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'data berhasil didapatkan',
            'data' => $transaksi
        ]);
    }

    public function orderUserById(Request $request, $id, \App\Services\Transaksi\Actions\GetOrderUserByIdAction $action)
    {
        $user = $request->user();

        if (!$user->can('read order user')) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        try {
            $data = $action->execute($request, $user, $id);

            return response()->json([
                'status' => 'success',
                'message' => 'data berhasil didapatkan',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 404);
        }
    }

    public function orderGetAll(Request $request, \App\Services\Transaksi\Actions\GetAllOrdersAction $action)
    {
        $transaksi = $action->execute($request);

        return response()->json([
            'status' => 'success',
            'message' => 'data berhasil didapatkan',
            'data' => $transaksi
        ]);
    }

    public function getOnlineDriver(Request $request, \App\Services\Transaksi\Actions\GetOnlineDriverAction $action)
    {
        $user = $request->user();
        $permission = $user->can('read online driver');

        if (!$permission) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        $result = $action->execute($request);

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil mengambil data driver online',
            'data' => $result,
        ]);
    }

    public function orderTenant(Request $request, \App\Services\Transaksi\Actions\GetOrderTenantAction $action)
    {
        $user = $request->user();
        $permission = $user->can('read order tenant');

        if (!$permission) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        try {
            $transaksi = $action->execute($request, $user);

            return response()->json([
                'status' => 'success',
                'message' => 'data berhasil didapatkan',
                'data' => $transaksi
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'failed',
                'message' => $e->getMessage()
            ], $e->getCode() ?: 404);
        }
    }

    public function orderMasbro(Request $request, \App\Services\Transaksi\Actions\GetOrderMasbroAction $action)
    {
        $user = $request->user();

        if (!$user->can('read order driver')) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        $transaksi = $action->execute($request, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'data berhasil didapatkan',
            'data' => $transaksi
        ]);
    }



    public function store(
        Request $request,
        \App\Services\Transaksi\Actions\CreateMultitenantOrderAction $multitenantAction,
        \App\Services\Transaksi\Actions\CreateOrderAction $singleAction
    ) {
        $user = $request->user();
        $permission = $user->can('create order user');
        $permission = true;

        if (!$permission) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        $validatator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'isAntar' => 'required|boolean',
            'ruangan_id' => 'required_if:isAntar,true',
            'metode_pembayaran' => 'required|in:koin,cod,qris',
            'catatan' => 'nullable',
            'menus' => 'required|array',
            'menus.*.id' => 'required|integer|exists:menus,id',
            'menus.*.jumlah' => 'required|integer|min:1',
            'catatan_lokasi_pengantaran' => 'nullable|string|max:255',
        ]);

        if ($validatator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => $validatator->errors()->all()
            ], 400);
        }

        $menu_ids = collect($request->menus)->pluck('id')->toArray();
        $tenants = \App\Models\Menus::withTrashed()->whereIn('id', $menu_ids)
            ->pluck('tenant_id')
            ->unique();

        // if ($tenants->count() > 2) {
        //     return response()->json([
        //         'status' => 'failed',
        //         'message' => 'Maksimal pesan dari 2 toko',
        //     ], 400);
        // }

        $menuFirst = \App\Models\Menus::withTrashed()->with('tenant.pemilik')->find($menu_ids[0]);

        if (!$menuFirst || !$menuFirst->tenant) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Tenant tidak ditemukan'
            ], 404);
        }

        $tenant = $menuFirst->tenant;

        if ($tenant->isOnline == 0) {
            return response()->json([
                'status' => 'failed',
                'message' => ['Toko sedang tutup']
            ], 400);
        }

        $jumlahDriver = \App\Models\User::where('isOnline', true)
            ->whereHas('roles', function ($q) {
                $q->where('name', 'masbro');
            })->count();

        if ($request->isAntar && $jumlahDriver == 0) {
            return response()->json([
                'status' => 'failed',
                'message' => ['Tidak ada driver online saat ini. Silakan coba lagi nanti.']
            ], 400);
        }

        $menusNotReady = \App\Models\Menus::withTrashed()
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

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            if ($tenants->count() > 1) {
                return $multitenantAction->execute($request, $user, $tenants);
            } else {
                return $singleAction->execute($request, $user, $tenant, $menuFirst);
            }
        } catch (\Throwable $th) {
            \Illuminate\Support\Facades\DB::rollback();
            \Illuminate\Support\Facades\Log::error('Transaksi gagal: ' . $th->getMessage());
            \Illuminate\Support\Facades\Log::error('Trace: ' . $th->getTraceAsString());

            return response()->json([
                'status' => 'failed',
                'messages' => 'transaksi gagal: ' . $th->getMessage(),
            ], 400);
        }
    }

    private function getOngkirGedung($ruanganId, $isMultitenant = false)
    {
        $ruangan = Ruangan::with('gedung')->find($ruanganId);
        if (!$ruangan || !$ruangan->gedung)
            return 0;

        return $isMultitenant
            ? ($ruangan->gedung->ongkir_multitenant ?? 0)
            : ($ruangan->gedung->ongkir ?? 0);
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

    public function webHookMidtrans(Request $request, \App\Services\Transaksi\Actions\ProcessWebhookMidtransAction $action)
    {
        $action->execute($request);
    }

    public function refund(Transaksi $transaksi, \App\Services\Transaksi\Actions\ProcessRefundAction $action)
    {
        return $action->execute($transaksi);
    }

    public function cancel(Request $request, $id, \App\Services\Transaksi\Actions\CancelOrderAction $action)
    {
        return $action->execute($request, $id);
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

    public function pushToUbisma(Request $request, \App\Services\Transaksi\Actions\PushToUbismaAction $action)
    {
        return $action->execute($request);
    }

    public function storeTopUp(Request $request, \App\Services\Transaksi\Actions\StoreTopUpAction $action)
    {
        return $action->execute($request);
    }

    private function generateBiayaAdmin(int $nominalTopup): array
    {
        $persentaseBiayaMidtrans = 0.007; // 0,7%

        $biayaMidtrans = (int) ceil($nominalTopup * $persentaseBiayaMidtrans);
        $totalSebelumBulat = $nominalTopup + $biayaMidtrans;
        $totalBayar = (int) (ceil($totalSebelumBulat / 50) * 50);
        $biayaUbsima = $totalBayar - $totalSebelumBulat;
        $totalBiayaAdmin = $biayaMidtrans + $biayaUbsima;

        return [
            'nominal_topup' => $nominalTopup,
            'biaya_midtrans' => $biayaMidtrans,
            'biaya_ubsima' => $biayaUbsima,
            'total_biaya_admin' => $totalBiayaAdmin,
            'total_bayar_user' => $totalBayar,
            'total_sebelum_bulat' => $totalSebelumBulat
        ];
    }

    public function midtransTopUp(Request $request, \App\Services\Transaksi\Actions\CreateMidtransTopUpAction $action)
    {
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
        return $action->execute($request, $biaya);
    }

    public function midtransGetTopUp($midtransRequestId, \App\Services\Transaksi\Actions\CheckMidtransTopUpAction $action)
    {
        return $action->execute($midtransRequestId);
    }

    public function getTopUp($kodeBayar, \App\Services\Transaksi\Actions\GetTopUpAction $action)
    {
        return $action->execute($kodeBayar);
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

    public function sendMessage($transaksiId, Request $request, \App\Services\Transaksi\Actions\SendMessageAction $action)
    {
        return $action->execute($transaksiId, $request);
    }

    public function getMessageDriverToBuyer($transaksiId, \App\Services\Transaksi\Actions\GetMessageDriverToBuyerAction $action)
    {
        return $action->execute($transaksiId);
    }

    public function getMessageTenantToBuyer($transaksiId, \App\Services\Transaksi\Actions\GetMessageTenantToBuyerAction $action)
    {
        return $action->execute($transaksiId);
    }

    public function getLeaderboardDriver(\App\Services\Transaksi\Actions\GetLeaderboardDriverAction $action)
    {
        $leaderboard = $action->execute(request());

        return response()->json([
            'status' => true,
            'data' => $leaderboard,
        ]);
    }

    public function pushNotificationDriverToBuyer(Request $request, $transaksiId, \App\Services\Transaksi\Actions\PushNotificationDriverToBuyerAction $action)
    {
        return $action->execute($request, $transaksiId);
    }

    public function handleCallback(Request $request, \App\Services\Transaksi\Actions\HandlePaymentCallbackAction $action)
    {
        return $action->execute($request);
    }

    public function getPenghasilanTenant(Request $request, \App\Services\Transaksi\Actions\GetPenghasilanTenantAction $action)
    {
        $result = $action->execute($request);

        if ($result['year'] && !$result['month'] && !$result['date']) {
            return response()->json([
                'labels' => $result['labels'],
                'selesaiData' => array_map('intval', $result['selesaiData']),
                'refundData' => array_map('intval', $result['refundData']),
                'totalSelesai' => intval($result['totalSelesai']),
                'totalRefund' => intval($result['totalRefund']),
                'totalPendapatan' => intval($result['totalPendapatan']),
                'transaksi' => collect($result['transaksiList'])->map(function ($trx) {
                    return [
                        'id' => intval($trx['id']),
                        'status' => $trx['status'],
                        'harga' => intval($trx['harga']),
                        'pendapatan_bersih' => intval($trx['pendapatan_bersih']),
                        'tanggal' => $trx['tanggal'],
                        'label' => $trx['label'],
                        'label_tanggal' => $trx['label_tanggal'],
                    ];
                }),
            ]);
        } elseif ($result['year'] && $result['month'] && !$result['date']) {
            return response()->json([
                'labels' => $result['labels'],
                'selesaiData' => array_map('intval', $result['selesaiData']),
                'refundData' => array_map('intval', $result['refundData']),
                'totalSelesai' => intval($result['totalSelesai']),
                'totalRefund' => intval($result['totalRefund']),
                'totalPendapatan' => intval($result['totalPendapatan']),
                'transaksi' => collect($result['transaksiList'])->map(function ($trx) {
                    return [
                        'id' => intval($trx['id']),
                        'status' => $trx['status'],
                        'harga' => intval($trx['harga']),
                        'pendapatan_bersih' => intval($trx['pendapatan_bersih']),
                        'tanggal' => $trx['tanggal'],
                        'label' => $trx['label'],
                        'label_tanggal' => $trx['label_tanggal'],
                    ];
                }),
            ]);
        } else {
            return response()->json([
                'labels' => $result['labels'],
                'selesaiData' => array_map('intval', $result['selesaiData']),
                'refundData' => array_map('intval', $result['refundData']),
                'totalSelesai' => intval($result['totalSelesai']),
                'totalRefund' => intval($result['totalRefund']),
                'totalPendapatan' => intval($result['totalPendapatan']),
                'transaksi' => collect($result['transaksiList'])->map(function ($trx) {
                    return [
                        'id' => intval($trx['id']),
                        'status' => $trx['status'],
                        'harga' => intval($trx['harga']),
                        'pendapatan_bersih' => intval($trx['pendapatan_bersih']),
                        'tanggal' => $trx['tanggal'],
                        'label' => $trx['label'],
                    ];
                }),
            ]);
        }
    }
}
