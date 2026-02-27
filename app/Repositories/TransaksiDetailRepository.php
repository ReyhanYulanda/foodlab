<?php

namespace App\Repositories;

use App\Models\TransaksiDetail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TransaksiDetailRepository
{
    public function getTodaysGrossIncome(int $tenantId)
    {
        return TransaksiDetail::whereHas('menus', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->whereHas('transaksi', function ($q) {
            $q->where('status', 'selesai');
            $q->whereDate('updated_at', Carbon::today());
        })->sum(DB::raw('harga * jumlah'));
    }
}
