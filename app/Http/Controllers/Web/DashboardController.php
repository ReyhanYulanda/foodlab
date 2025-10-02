<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Cashback;
use App\Models\Ruangan;
use Illuminate\Http\Request;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{

    public function index(Request $request)
    {
        $mode = $request->get('mode', 'weekly'); // default weekly

        // Data untuk dropdown
        $modes = [
            'weekly' => 'Per Tanggal (Minggu ini)',
            'monthly' => 'Per Minggu (Bulan ini)',
            'yearly' => 'Per Bulan (Tahun ini)',
            'all' => 'Per Tahun (All Time)',
        ];

        $labels = [];
        $selesaiData = [];
        $refundData = [];

        if ($mode === 'weekly') {
            // x = tanggal minggu ini
            $start = now()->startOfWeek();
            $end = now()->endOfWeek();

            $period = \Carbon\CarbonPeriod::create($start, $end);

            foreach ($period as $date) {
                $labels[] = $date->format('d M');
                $selesaiData[] = Transaksi::whereDate('created_at', $date)->where('status', 'selesai')->count();
                $refundData[] = Transaksi::whereDate('created_at', $date)->where('status', 'refund_selesai')->count();
            }
        } elseif ($mode === 'monthly') {
            // x = minggu dalam bulan ini
            $start = now()->startOfMonth();
            $end = now()->endOfMonth();
            $week = 1;

            while ($start <= $end) {
                $weekStart = $start->copy();
                $weekEnd = $start->copy()->endOfWeek();

                $labels[] = "Minggu $week";
                $selesaiData[] = Transaksi::whereBetween('created_at', [$weekStart, $weekEnd])->where('status', 'selesai')->count();
                $refundData[] = Transaksi::whereBetween('created_at', [$weekStart, $weekEnd])->where('status', 'refund_selesai')->count();

                $start->addWeek();
                $week++;
            }
        } elseif ($mode === 'yearly') {
            // x = bulan
            for ($m = 1; $m <= 12; $m++) {
                $labels[] = date('M', mktime(0, 0, 0, $m, 1));
                $selesaiData[] = Transaksi::whereMonth('created_at', $m)->whereYear('created_at', now()->year)->where('status', 'selesai')->count();
                $refundData[] = Transaksi::whereMonth('created_at', $m)->whereYear('created_at', now()->year)->where('status', 'refund_selesai')->count();
            }
        } else {
            $years = Transaksi::selectRaw('YEAR(created_at) as year')->distinct()->pluck('year');
            foreach ($years as $y) {
                $labels[] = $y;
                $selesaiData[] = Transaksi::whereYear('created_at', $y)
                    ->where('status', 'selesai')
                    ->count();
                $refundData[] = Transaksi::whereYear('created_at', $y)
                    ->where('status', 'refund_selesai')
                    ->count();
            }
        }

        $totalSelesai = Transaksi::where('status', 'selesai')->count();
        $totalRefund = Transaksi::where('status', 'refund_selesai')->count();

        $refundList = \App\Models\TransaksiDetail::selectRaw('tenants.nama_tenant, SUM(transaksi_detail.jumlah) as total_refund')
            ->join('transaksi', 'transaksi.id', '=', 'transaksi_detail.transaksi_id')
            ->join('menus', 'menus.id', '=', 'transaksi_detail.menu_id')
            ->join('tenants', 'tenants.id', '=', 'menus.tenant_id')
            ->where('transaksi.status', 'refund_selesai')
            ->whereNull('transaksi.deleted_at')
            ->whereNull('transaksi_detail.deleted_at')
            ->groupBy('tenants.nama_tenant')
            ->get();

        return view('dashboard', compact(
            'labels',
            'selesaiData',
            'refundData',
            'totalSelesai',
            'totalRefund',
            'modes',
            'mode',
            'refundList'
        ));
    }
}
