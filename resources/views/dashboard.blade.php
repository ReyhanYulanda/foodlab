<x-master-layout>
    @push('cssLibrary')
        <link rel="stylesheet" href="{{ asset('vendor/chart.js/Chart.min.css') }}">
        <style>
            .stat-card {
                transition: transform 0.2s;
            }

            .stat-card:hover {
                transform: translateY(-5px);
            }

            .icon-box {
                width: 48px;
                height: 48px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
            }

            .bg-light-primary {
                background-color: rgba(67, 94, 190, 0.1);
                color: #435ebe;
            }

            .bg-light-success {
                background-color: rgba(40, 167, 69, 0.1);
                color: #28a745;
            }

            .bg-light-warning {
                background-color: rgba(255, 193, 7, 0.1);
                color: #ffc107;
            }

            .bg-light-danger {
                background-color: rgba(220, 53, 69, 0.1);
                color: #dc3545;
            }

        </style>
    @endpush

    <div class="main-content">
        <div class="title mb-4">
            <h3 class="fw-bold">Dashboard Overview</h3>
            <p class="text-muted">Welcome back! Here is what's happening today.</p>
        </div>

        <div class="content-wrapper">
            {{-- STATS ROW --}}
            <div class="row mb-4">
                <div class="col-6 col-lg-3 col-md-6">
                    <div class="card stat-card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-box bg-light-success me-3">
                                    <i class="fas fa-dollar-sign"></i> 💰
                                </div>
                                <h6 class="mb-0 text-muted">Total Pendapatan</h6>
                            </div>
                            <h4 class="fw-bold mb-0">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</h4>
                            <small class="text-success"><i class="fas fa-arrow-up"></i> Lifetime</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 col-md-6">
                    <div class="card stat-card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-box bg-light-primary me-3">
                                    <i class="fas fa-shopping-bag"></i> 🛍️
                                </div>
                                <h6 class="mb-0 text-muted">Transaksi Selesai</h6>
                            </div>
                            <h4 class="fw-bold mb-0">{{ number_format($totalSelesai, 0, ',', '.') }}</h4>
                            <small class="text-primary">Transaksi berhasil</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 col-md-6">
                    <div class="card stat-card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-box bg-light-warning me-3">
                                    <i class="fas fa-clock"></i> ⏳
                                </div>
                                <h6 class="mb-0 text-muted">Sedang Aktif</h6>
                            </div>
                            <h4 class="fw-bold mb-0">{{ number_format($activeTransactions, 0, ',', '.') }}</h4>
                            <small class="text-warning">Perlu diproses</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 col-md-6">
                    <div class="card stat-card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-box bg-light-danger me-3">
                                    <i class="fas fa-undo"></i> ↩️
                                </div>
                                <h6 class="mb-0 text-muted">Refund</h6>
                            </div>
                            <h4 class="fw-bold mb-0">{{ number_format($totalRefund, 0, ',', '.') }}</h4>
                            <small class="text-danger">Transaksi dibatalkan</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                {{-- MAIN CHART --}}
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm" style="min-height: 400px;">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center pt-4">
                            <h5 class="fw-bold">Analisis Transaksi & Pendapatan</h5>
                            <form method="GET" action="{{ route('dashboard') }}" class="d-inline-block"
                                id="dashboardFilterForm">
                                {{-- Preserve refund filters --}}
                                <input type="hidden" name="refund_month" value="{{ $refundMonth }}">
                                <input type="hidden" name="refund_year" value="{{ $refundSelectedYear }}">
                                <div class="d-flex gap-2">
                                    <select name="mode" onchange="this.form.submit()" class="form-select form-select-sm"
                                        style="width: auto;">
                                        @foreach ($modes as $key => $label)
                                            <option value="{{ $key }}" {{ $mode == $key ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <select name="year" onchange="this.form.submit()" class="form-select form-select-sm"
                                        style="width: auto;">
                                        @foreach ($availableYears as $year)
                                            <option value="{{ $year }}" {{ $selectedYear == $year ? 'selected' : '' }}>
                                                Tahun {{ $year }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </form>
                        </div>
                        <div class="card-body">
                            <canvas id="mainChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- TOP TENANTS --}}
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm" style="min-height: 400px;">
                        <div class="card-header bg-white pt-4">
                            <h5 class="fw-bold">Top 5 Tenants</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="tenantChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                {{-- RECENT TRANSACTIONS --}}
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white pt-4">
                            <h5 class="fw-bold">Transaksi Terbaru</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="ps-4">Order ID</th>
                                            <th>Pelanggan</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Waktu</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($recentTransactions as $transaction)
                                            <tr>
                                                <td class="ps-4 fw-bold">#{{ substr($transaction->order_id, -6) }}</td>
                                                <td>{{ $transaction->nama_pembeli }}</td>
                                                <td>Rp {{ number_format($transaction->total, 0, ',', '.') }}</td>
                                                <td>
                                                    @php
                                                        switch ($transaction->status) {
                                                            case 'selesai':
                                                                $badgeClass = 'success';
                                                                break;
                                                            case 'menunggu_konfirmasi':
                                                                $badgeClass = 'warning';
                                                                break;
                                                            case 'diproses':
                                                                $badgeClass = 'info';
                                                                break;
                                                            case 'diantar':
                                                                $badgeClass = 'primary';
                                                                break;
                                                            case 'refund_selesai':
                                                                $badgeClass = 'danger';
                                                                break;
                                                            default:
                                                                $badgeClass = 'secondary';
                                                                break;
                                                        }
                                                    @endphp
                                                    <span
                                                        class="badge bg-{{ $badgeClass }}">{{ str_replace('_', ' ', strtoupper($transaction->status)) }}</span>
                                                </td>
                                                <td class="text-muted small">{{ $transaction->created_at->diffForHumans() }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center py-4">Belum ada transaksi</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- REFUND MONITOR --}}
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white pt-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="fw-bold mb-0">Monitor Refund</h5>
                            </div>
                            <form method="GET" action="{{ route('dashboard') }}" class="d-inline-block"
                                id="refundFilterForm">
                                {{-- Preserve main chart filters --}}
                                <input type="hidden" name="mode" value="{{ $mode }}">
                                <input type="hidden" name="year" value="{{ $selectedYear }}">
                                <div class="d-flex gap-2">
                                    <select name="refund_month" onchange="this.form.submit()"
                                        class="form-select form-select-sm" style="width: auto;">
                                        <option value="" {{ !$refundMonth ? 'selected' : '' }}>Semua Bulan</option>
                                        @foreach ($refundMonths as $key => $label)
                                            <option value="{{ $key }}" {{ $refundMonth == $key ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <select name="refund_year" onchange="this.form.submit()"
                                        class="form-select form-select-sm" style="width: auto;">
                                        @foreach ($availableYears as $year)
                                            <option value="{{ $year }}" {{ $refundSelectedYear == $year ? 'selected' : '' }}>
                                                Tahun {{ $year }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </form>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="ps-4">Tenant</th>
                                            <th>Jml</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($refundList as $refund)
                                            <tr>
                                                <td class="ps-4">{{ $refund->nama_tenant }}</td>
                                                <td class="fw-bold text-danger">
                                                    {{ number_format($refund->total_refund, 0, ',', '.') }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="2" class="text-center py-3 text-muted">Tidak ada data refund
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    @push('js')
        <script src="{{ asset('vendor/chart.js/Chart.min.js') }}"></script>
        <script>
            // Initialize Colors
            const colors = {
                primary: '#435ebe',
                success: '#28a745',
                warning: '#ffc107',
                danger: '#dc3545',
                info: '#17a2b8',
                secondary: '#6c757d'
            };

            // Main Mixed Chart (Line for Revenue, Bar for Transactions)
            // Chart.js v2.9.4 Compatibility
            const ctxMain = document.getElementById('mainChart').getContext('2d');
            new Chart(ctxMain, {
                type: 'bar',
                data: {
                    labels: @json($labels),
                    datasets: [
                        {
                            label: 'Pesan Antar - Pendapatan',
                            data: @json($pesanAntarRevenueData),
                            type: 'line',
                            borderColor: colors.success,
                            backgroundColor: 'rgba(40, 167, 69, 0.08)',
                            borderWidth: 2,
                            pointRadius: 3,
                            fill: false,
                            yAxisID: 'y-axis-revenue'
                        },
                        {
                            label: 'Ambil Sendiri - Pendapatan',
                            data: @json($ambilSendiriRevenueData),
                            type: 'line',
                            borderColor: colors.warning,
                            backgroundColor: 'rgba(255, 193, 7, 0.08)',
                            borderWidth: 2,
                            pointRadius: 3,
                            fill: false,
                            yAxisID: 'y-axis-revenue'
                        },
                        {
                            label: 'Pesan Antar - Jumlah Transaksi',
                            data: @json($pesanAntarSelesaiData),
                            type: 'bar',
                            backgroundColor: 'rgba(40, 167, 69, 0.35)',
                            borderColor: colors.success,
                            borderWidth: 1,
                            yAxisID: 'y-axis-transactions'
                        },
                        {
                            label: 'Ambil Sendiri - Jumlah Transaksi',
                            data: @json($ambilSendiriSelesaiData),
                            type: 'bar',
                            backgroundColor: 'rgba(255, 193, 7, 0.45)',
                            borderColor: colors.warning,
                            borderWidth: 1,
                            yAxisID: 'y-axis-transactions'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    tooltips: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function (tooltipItem, data) {
                                var label = data.datasets[tooltipItem.datasetIndex].label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (data.datasets[tooltipItem.datasetIndex].yAxisID === 'y-axis-revenue') {
                                    label += 'Rp ' + tooltipItem.yLabel.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                                } else {
                                    label += tooltipItem.yLabel + ' transaksi';
                                }
                                return label;
                            }
                        }
                    },
                    hover: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        yAxes: [
                            {
                                id: 'y-axis-revenue',
                                type: 'linear',
                                position: 'left',
                                scaleLabel: {
                                    display: true,
                                    labelString: 'Pendapatan'
                                },
                                ticks: {
                                    callback: function (value, index, values) {
                                        return 'Rp ' + (value / 1000) + 'k';
                                    }
                                }
                            },
                            {
                                id: 'y-axis-transactions',
                                type: 'linear',
                                position: 'right',
                                gridLines: {
                                    drawOnChartArea: false
                                },
                                scaleLabel: {
                                    display: true,
                                    labelString: 'Jumlah Transaksi'
                                }
                            }
                        ]
                    },
                    legend: {
                        position: 'top'
                    },
                    title: {
                        display: false,
                        text: 'Analisis Transaksi'
                    }
                }
            });

            // Tenant Performance Chart (Doughnut)
            // Chart.js v2.9.4 Compatibility
            const ctxTenant = document.getElementById('tenantChart').getContext('2d');
            new Chart(ctxTenant, {
                type: 'doughnut',
                data: {
                    labels: @json($topTenantLabels),
                    datasets: [{
                        data: @json($topTenantData),
                        backgroundColor: [
                            colors.primary,
                            colors.success,
                            colors.warning,
                            colors.info,
                            colors.danger
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    cutoutPercentage: 70
                }
            });
        </script>
    @endpush
</x-master-layout>
