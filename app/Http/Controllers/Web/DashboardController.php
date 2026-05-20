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

        $createOrderTypeQuery = function (int $isAntar) {
            return Transaksi::query()
                ->when(
                    $isAntar === 1,
                    fn($query) => $query->where('isAntar', 1),
                    fn($query) => $query->where(fn($query) => $query->where('isAntar', '!=', 1)->orWhereNull('isAntar'))
                );
        };

        $countSelesaiByOrderType = function (callable $dateScope, int $isAntar) use ($createOrderTypeQuery) {
            $query = $createOrderTypeQuery($isAntar)->where('status', 'selesai');
            $dateScope($query);

            return $query->count();
        };

        $sumRevenueByOrderType = function (callable $dateScope, int $isAntar) use ($createOrderTypeQuery) {
            $query = $createOrderTypeQuery($isAntar)->where('status', 'selesai');
            $dateScope($query);

            return $query->sum('total');
        };

        // Get available years from transactions
        $availableYears = Transaksi::selectRaw('YEAR(created_at) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        // Determine the anchor date (latest transaction date or now if empty)
        // This ensures that if the data is old (e.g. 2025), the dashboard shows that period by default
        $latestTransaction = Transaksi::latest('created_at')->first();
        $defaultYear = $latestTransaction ? $latestTransaction->created_at->year : now()->year;

        // Get selected year from request, default to latest year with data
        $selectedYear = $request->get('year', $defaultYear);

        // Create anchor date based on selected year
        // For weekly/monthly modes, use the last day of the selected year to show the most recent data
        // For yearly mode, the year itself is what matters
        if ($mode === 'yearly' || $mode === 'all') {
            $anchorDate = now()->setYear($selectedYear)->startOfYear();
        } else {
            // For weekly/monthly, use end of year to show latest week/month of that year
            $anchorDate = now()->setYear($selectedYear)->endOfYear();
        }

        // Data untuk dropdown
        $modes = [
            'weekly' => 'Per Tanggal (Minggu ini)',
            'monthly' => 'Per Minggu (Bulan ini)',
            'yearly' => 'Per Bulan (Tahun ini)',
            'all' => 'Per Tahun (All Time)',
        ];

        $labels = [];
        $pesanAntarSelesaiData = [];
        $pesanAntarRevenueData = [];
        $ambilSendiriSelesaiData = [];
        $ambilSendiriRevenueData = [];

        $appendChartData = function (callable $dateScope) use (
            &$pesanAntarSelesaiData,
            &$pesanAntarRevenueData,
            &$ambilSendiriSelesaiData,
            &$ambilSendiriRevenueData,
            $countSelesaiByOrderType,
            $sumRevenueByOrderType
        ) {
            $pesanAntarSelesaiData[] = $countSelesaiByOrderType($dateScope, 1);
            $pesanAntarRevenueData[] = $sumRevenueByOrderType($dateScope, 1);
            $ambilSendiriSelesaiData[] = $countSelesaiByOrderType($dateScope, 0);
            $ambilSendiriRevenueData[] = $sumRevenueByOrderType($dateScope, 0);
        };

        if ($mode === 'weekly') {
            // x = tanggal minggu ini (relative to anchorDate)
            $start = $anchorDate->copy()->startOfWeek();
            $end = $anchorDate->copy()->endOfWeek();

            $period = \Carbon\CarbonPeriod::create($start, $end);

            foreach ($period as $date) {
                $labels[] = $date->format('d M');
                $appendChartData(fn($query) => $query->whereDate('created_at', $date));
            }
        } elseif ($mode === 'monthly') {
            // x = minggu dalam bulan ini (relative to anchorDate)
            $start = $anchorDate->copy()->startOfMonth();
            $end = $anchorDate->copy()->endOfMonth();
            $week = 1;

            while ($start <= $end) {
                $weekStart = $start->copy();
                $weekEnd = $start->copy()->endOfWeek();
                if ($weekEnd > $end)
                    $weekEnd = $end; // Ensure weekEnd does not exceed month end

                $labels[] = "Minggu $week";
                $appendChartData(fn($query) => $query->whereBetween('created_at', [$weekStart, $weekEnd]));

                $start->addWeek();
                $week++;
            }
        } elseif ($mode === 'yearly') {
            // x = bulan (relative to anchorDate year)
            $targetYear = $anchorDate->year;

            for ($m = 1; $m <= 12; $m++) {
                $labels[] = date('M', mktime(0, 0, 0, $m, 1));
                $appendChartData(fn($query) => $query->whereMonth('created_at', $m)->whereYear('created_at', $targetYear));
            }
        } else {
            $years = Transaksi::selectRaw('YEAR(created_at) as year')->distinct()->orderBy('year')->pluck('year');
            foreach ($years as $y) {
                $labels[] = $y;
                $appendChartData(fn($query) => $query->whereYear('created_at', $y));
            }
        }

        // Statistik Card Utama
        $totalSelesai = Transaksi::where('status', 'selesai')->count();
        $totalRefund = Transaksi::where('status', 'refund_selesai')->count();
        $totalRevenue = Transaksi::where('status', 'selesai')->sum('total'); // Keep this for overall total revenue
        $activeTransactions = Transaksi::whereIn('status', ['menunggu_konfirmasi', 'diproses', 'diantar'])->count();

        // Tenant yang diabaikan
        $ignoredTenants = ['Kedai Pak Agil', 'Test Tenant'];

        // Top 5 Tenant berdasarkan Pendapatan (Revenue)
        $topTenants = DB::table('transaksi')
            ->join('transaksi_detail', 'transaksi.id', '=', 'transaksi_detail.transaksi_id')
            ->join('menus', 'menus.id', '=', 'transaksi_detail.menu_id')
            ->join('tenants', 'tenants.id', '=', 'menus.tenant_id')
            ->where('transaksi.status', 'selesai')
            ->whereNotIn('tenants.nama_tenant', $ignoredTenants)
            ->select('tenants.nama_tenant', DB::raw('SUM(transaksi_detail.harga) as total_revenue'))
            ->groupBy('tenants.nama_tenant')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        $topTenantLabels = $topTenants->pluck('nama_tenant');
        $topTenantData = $topTenants->pluck('total_revenue');

        // Recent Transactions
        $recentTransactions = Transaksi::with(['user', 'ruangan'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Refund Monitor Filter
        $refundMonth = $request->get('refund_month');
        $refundSelectedYear = $request->get('refund_year', $defaultYear);

        $refundMonths = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $refundQuery = DB::table('transaksi')
            ->join('transaksi_detail', 'transaksi.id', '=', 'transaksi_detail.transaksi_id')
            ->join('menus', 'menus.id', '=', 'transaksi_detail.menu_id')
            ->join('tenants', 'tenants.id', '=', 'menus.tenant_id')
            ->where('transaksi.status', 'refund_selesai')
            ->whereNull('transaksi.deleted_at')
            ->whereNull('transaksi_detail.deleted_at')
            ->whereNotIn('tenants.nama_tenant', $ignoredTenants);

        // Apply date filter to refund query
        $refundQuery->whereYear('transaksi.created_at', $refundSelectedYear);
        if ($refundMonth) {
            $refundQuery->whereMonth('transaksi.created_at', $refundMonth);
        }

        $refundList = $refundQuery
            ->select(
                'tenants.user_id as tenant_user_id',
                'tenants.nama_tenant',
                DB::raw('COUNT(DISTINCT transaksi.id) as total_refund')
            )
            ->groupBy('tenants.user_id', 'tenants.nama_tenant')
            ->orderByDesc('total_refund')
            ->get();

        return view('dashboard', compact(
            'labels',
            'pesanAntarSelesaiData',
            'pesanAntarRevenueData',
            'ambilSendiriSelesaiData',
            'ambilSendiriRevenueData',
            'totalSelesai',
            'totalRefund',
            'totalRevenue',
            'activeTransactions',
            'topTenantLabels',
            'topTenantData',
            'recentTransactions',
            'modes',
            'mode',
            'refundList',
            'refundMonths',
            'refundMonth',
            'refundSelectedYear',
            'availableYears',
            'selectedYear'
        ));
    }
}
