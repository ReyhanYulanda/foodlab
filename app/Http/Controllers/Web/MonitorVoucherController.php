<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CatatVoucher;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitorVoucherController extends Controller
{
    public function monitorVoucher()
    {
        // ambil data dari catat_voucher beserta relasinya
        $catatVouchers = CatatVoucher::with(['user', 'transaksi', 'voucher.cashback'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('pages.monitor-voucher.index', compact('catatVouchers'));
    }
}
