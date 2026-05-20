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

        // Get available years from transactions
        $availableYears = Transaksi::selectRaw('YEAR(created_at) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        // Default dashboard period follows today's date.
        $today = now();
        $defaultYear = $today->year;

        // Get selected year from request, default to latest year with data
        $selectedYear = (int) $request->get('year', $defaultYear);

        if (!in_array($selectedYear, array_map('intval', $availableYears), true)) {
            $availableYears[] = $selectedYear;
            rsort($availableYears);
        }

        $monthNames = [
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

        $defaultMonth = $selectedYear === $today->year ? $today->month : 1;
        $selectedMonth = (int) $request->get('chart_month', $defaultMonth);

        if ($selectedMonth < 1 || $selectedMonth > 12) {
            $selectedMonth = $defaultMonth;
        }

        $selectedMonthDate = \Carbon\Carbon::create($selectedYear, $selectedMonth, 1);
        $chartWeekCount = (int) ceil($selectedMonthDate->daysInMonth / 7);
        $defaultWeek = $selectedYear === $today->year && $selectedMonth === $today->month
            ? (int) ceil($today->day / 7)
            : 1;
        $selectedWeek = (int) $request->get('chart_week', $defaultWeek);

        if ($selectedWeek < 1 || $selectedWeek > $chartWeekCount) {
            $selectedWeek = 1;
        }

        $selectedMonthName = $monthNames[$selectedMonth];
        $previousChartMonth = $selectedMonth === 1 ? 12 : $selectedMonth - 1;
        $previousChartYear = $selectedMonth === 1 ? $selectedYear - 1 : $selectedYear;
        $nextChartMonth = $selectedMonth === 12 ? 1 : $selectedMonth + 1;
        $nextChartYear = $selectedMonth === 12 ? $selectedYear + 1 : $selectedYear;
        $selectedWeekName = "Minggu $selectedWeek";

        if ($selectedWeek === 1) {
            $previousWeekMonthDate = $selectedMonthDate->copy()->subMonthNoOverflow();
            $previousChartWeek = (int) ceil($previousWeekMonthDate->daysInMonth / 7);
            $previousChartWeekMonth = $previousWeekMonthDate->month;
            $previousChartWeekYear = $previousWeekMonthDate->year;
        } else {
            $previousChartWeek = $selectedWeek - 1;
            $previousChartWeekMonth = $selectedMonth;
            $previousChartWeekYear = $selectedYear;
        }

        if ($selectedWeek === $chartWeekCount) {
            $nextWeekMonthDate = $selectedMonthDate->copy()->addMonthNoOverflow();
            $nextChartWeek = 1;
            $nextChartWeekMonth = $nextWeekMonthDate->month;
            $nextChartWeekYear = $nextWeekMonthDate->year;
        } else {
            $nextChartWeek = $selectedWeek + 1;
            $nextChartWeekMonth = $selectedMonth;
            $nextChartWeekYear = $selectedYear;
        }

        // Create anchor date based on selected year
        // For weekly/monthly modes, use the selected month to keep the chart navigable
        // For yearly mode, the year itself is what matters
        if ($mode === 'yearly' || $mode === 'all') {
            $anchorDate = now()->setYear($selectedYear)->startOfYear();
        } else {
            $anchorDate = $selectedMonthDate->copy()->startOfMonth();
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
        $chartPeriods = [];
        $chartRows = collect();

        if ($mode === 'weekly') {
            // x = tanggal minggu yang dipilih pada bulan yang dipilih
            $monthEnd = $anchorDate->copy()->endOfMonth();
            $start = $anchorDate->copy()->startOfMonth()->addDays(($selectedWeek - 1) * 7);
            $end = $start->copy()->addDays(6);
            if ($end > $monthEnd)
                $end = $monthEnd;

            $period = \Carbon\CarbonPeriod::create($start, $end);

            foreach ($period as $date) {
                $labels[] = $date->format('d M');
                $chartPeriods[] = [
                    'keys' => [$date->format('Y-m-d')],
                ];
            }

            $chartRows = Transaksi::query()
                ->selectRaw('DATE(created_at) as period_key')
                ->selectRaw('CASE WHEN isAntar = 1 THEN 1 ELSE 0 END as order_type')
                ->selectRaw('COUNT(*) as total_transactions')
                ->selectRaw('COALESCE(SUM(total), 0) as total_revenue')
                ->where('status', 'selesai')
                ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
                ->groupByRaw('DATE(created_at), CASE WHEN isAntar = 1 THEN 1 ELSE 0 END')
                ->get();
        } elseif ($mode === 'monthly') {
            // x = minggu dalam bulan ini (relative to anchorDate)
            $start = $anchorDate->copy()->startOfMonth();
            $end = $anchorDate->copy()->endOfMonth();
            $week = 1;

            while ($start <= $end) {
                $weekStart = $start->copy();
                $weekEnd = $start->copy()->addDays(6);
                if ($weekEnd > $end)
                    $weekEnd = $end; // Ensure weekEnd does not exceed month end

                $labels[] = "Minggu $week";
                $chartPeriods[] = [
                    'keys' => collect(\Carbon\CarbonPeriod::create($weekStart, $weekEnd))
                        ->map(fn($date) => $date->format('Y-m-d'))
                        ->toArray(),
                ];

                $start = $weekEnd->copy()->addDay();
                $week++;
            }

            $chartRows = Transaksi::query()
                ->selectRaw('DATE(created_at) as period_key')
                ->selectRaw('CASE WHEN isAntar = 1 THEN 1 ELSE 0 END as order_type')
                ->selectRaw('COUNT(*) as total_transactions')
                ->selectRaw('COALESCE(SUM(total), 0) as total_revenue')
                ->where('status', 'selesai')
                ->whereBetween('created_at', [$anchorDate->copy()->startOfMonth(), $anchorDate->copy()->endOfMonth()])
                ->groupByRaw('DATE(created_at), CASE WHEN isAntar = 1 THEN 1 ELSE 0 END')
                ->get();
        } elseif ($mode === 'yearly') {
            // x = bulan (relative to anchorDate year)
            $targetYear = $anchorDate->year;

            for ($m = 1; $m <= 12; $m++) {
                $labels[] = date('M', mktime(0, 0, 0, $m, 1));
                $chartPeriods[] = [
                    'keys' => [(string) $m],
                ];
            }

            $chartRows = Transaksi::query()
                ->selectRaw('MONTH(created_at) as period_key')
                ->selectRaw('CASE WHEN isAntar = 1 THEN 1 ELSE 0 END as order_type')
                ->selectRaw('COUNT(*) as total_transactions')
                ->selectRaw('COALESCE(SUM(total), 0) as total_revenue')
                ->where('status', 'selesai')
                ->whereYear('created_at', $targetYear)
                ->groupByRaw('MONTH(created_at), CASE WHEN isAntar = 1 THEN 1 ELSE 0 END')
                ->get();
        } else {
            $years = Transaksi::selectRaw('YEAR(created_at) as year')->distinct()->orderBy('year')->pluck('year');
            foreach ($years as $y) {
                $labels[] = $y;
                $chartPeriods[] = [
                    'keys' => [(string) $y],
                ];
            }

            $chartRows = Transaksi::query()
                ->selectRaw('YEAR(created_at) as period_key')
                ->selectRaw('CASE WHEN isAntar = 1 THEN 1 ELSE 0 END as order_type')
                ->selectRaw('COUNT(*) as total_transactions')
                ->selectRaw('COALESCE(SUM(total), 0) as total_revenue')
                ->where('status', 'selesai')
                ->groupByRaw('YEAR(created_at), CASE WHEN isAntar = 1 THEN 1 ELSE 0 END')
                ->get();
        }

        $chartStats = [];
        foreach ($chartRows as $row) {
            $chartStats[(string) $row->period_key][(int) $row->order_type] = [
                'transactions' => (int) $row->total_transactions,
                'revenue' => (float) $row->total_revenue,
            ];
        }

        foreach ($chartPeriods as $period) {
            $pesanAntarTransactions = 0;
            $pesanAntarRevenue = 0;
            $ambilSendiriTransactions = 0;
            $ambilSendiriRevenue = 0;

            foreach ($period['keys'] as $key) {
                $pesanAntarTransactions += $chartStats[$key][1]['transactions'] ?? 0;
                $pesanAntarRevenue += $chartStats[$key][1]['revenue'] ?? 0;
                $ambilSendiriTransactions += $chartStats[$key][0]['transactions'] ?? 0;
                $ambilSendiriRevenue += $chartStats[$key][0]['revenue'] ?? 0;
            }

            $pesanAntarSelesaiData[] = $pesanAntarTransactions;
            $pesanAntarRevenueData[] = $pesanAntarRevenue;
            $ambilSendiriSelesaiData[] = $ambilSendiriTransactions;
            $ambilSendiriRevenueData[] = $ambilSendiriRevenue;
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

        $refundMonths = $monthNames;

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
            'selectedYear',
            'selectedMonth',
            'selectedMonthName',
            'selectedWeek',
            'selectedWeekName',
            'previousChartMonth',
            'previousChartYear',
            'nextChartMonth',
            'nextChartYear',
            'previousChartWeek',
            'previousChartWeekMonth',
            'previousChartWeekYear',
            'nextChartWeek',
            'nextChartWeekMonth',
            'nextChartWeekYear'
        ));
    }
}
