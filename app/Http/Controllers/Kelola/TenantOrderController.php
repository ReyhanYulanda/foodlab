<?php

namespace App\Http\Controllers\Kelola;

use App\Http\Controllers\Controller;
use App\Models\Tenants;
use App\Models\Transaksi;
use App\Models\User;
use App\Response\ResponseApi;
use App\Services\Firebases;
use App\Services\Midtrans;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Helper\ValidationHelper;
use App\Services\Kelola\TenantOrderService;
use Throwable;

class TenantOrderController extends Controller
{

    protected $tenantOrderService;

    public function __construct(TenantOrderService $tenantOrderService)
    {
        $this->tenantOrderService = $tenantOrderService;
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->can('read order tenant')) {
            return ResponseApi::forbidden('tidak memiliki akses');  
        }

        try {
            $dataPesanan = $this->tenantOrderService->getDataPesanan($user->id, $request->status);

            return ResponseApi::success($dataPesanan, "Berhasil mengambil data");

        } catch (Throwable $th) {
            Log::error($th->getMessage());
            return ResponseApi::serverError();
        }
    }

    public function update(Request $request, Firebases $firebases, $id)
    {
        $user = $request->user();

        if (!$user->can('update order tenant')) {
            return ResponseApi::forbidden('tidak memiliki akses');
        }

        return $this->tenantOrderService->updateStatusPesanan($request, $firebases, $id);
    }
}
