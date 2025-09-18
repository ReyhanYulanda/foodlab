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
        $transaksis = Transaksi::with(['user', 'voucher.cashback'])
            ->where('cashback_amount', '>', 0)
            ->get();

        return view('pages.monitor-voucher.index', compact('transaksis'));
    }
}
