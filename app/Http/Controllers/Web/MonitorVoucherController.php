<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitorVoucherController extends Controller
{
    public function monitorVoucher()
    {
        // Ambil transaksi yang punya cashback
        $transaksis = Transaksi::with(['user', 'voucher'])
            ->where('cashback_amount', '>', 0)
            ->get(['id', 'user_id', 'voucher_id', 'cashback_amount']);

        // Mapping data sesuai kebutuhan
        $data = $transaksis->map(function ($trx) {
            return [
                'cashback_amount' => $trx->cashback_amount,
                'user_name'       => $trx->user ? $trx->user->name : null,
                'voucher_id'      => $trx->voucher_id,
            ];
        });

        return view('pages.monitor-voucher.index', compact('data'));
    }
}
