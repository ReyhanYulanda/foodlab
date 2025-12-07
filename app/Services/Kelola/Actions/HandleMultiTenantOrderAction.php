<?php

namespace App\Services\Kelola\Actions;

use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HandleMultiTenantOrderAction
{
    public function execute(Request $request, $transaksi): void
    {
        if (
            $transaksi->multitenant_id &&
            $transaksi->status === 'pesanan_diproses' &&
            $request->status === 'siap_diantar'
        ) {
            if ($transaksi->driver_id !== null) {
                $hasDelivered = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
                    ->where('id', '!=', $transaksi->id)
                    ->where('status', 'diantar')
                    ->whereNotNull('driver_id')
                    ->exists();

                if ($hasDelivered) {
                    $request->merge(['status' => 'diantar']);
                    Log::info("Multitenant {$transaksi->multitenant_id} otomatis skip ke 'diantar' karena sudah ada pesanan lain yang diantar.");
                }
            }
        }
    }
}
