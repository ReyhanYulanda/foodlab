<x-master-layout>
    @push('cssLibrary')
        <link rel="stylesheet" href="{{ asset('vendor/chart.js/Chart.min.css') }}">
    @endpush

    <div class="main-content">
        <div class="title">Dashboard</div>
        <div class="content-wrapper">
            {{-- ROW ATAS --}}
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Statistik Bulanan</h4>
                        </div>
                        <div class="card-body">
                            <canvas id="chartBulanan"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ROW BAWAH --}}
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4>Statistik Selesai vs Refund</h4>
                            <form method="GET" action="{{ route('dashboard') }}">
                                <select name="mode" onchange="this.form.submit()" class="form-select">
                                    @foreach ($modes as $key => $label)
                                        <option value="{{ $key }}" {{ $mode == $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        </div>
                        <div class="card-body">
                            <canvas id="chartCompare"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-wrapper mt-4">
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Total Transaksi (All Time)</h4>
                            </div>
                            <div class="card-body">
                                <canvas id="donutChart" height="120"></canvas>
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
            // Chart perbandingan selesai vs refund (weekly / monthly / yearly / all)
            const ctxCompare = document.getElementById('chartCompare').getContext('2d');
            new Chart(ctxCompare, {
                type: 'bar',
                data: {
                    labels: @json($labels),
                    datasets: [{
                            label: 'Selesai',
                            data: @json($selesaiData),
                            backgroundColor: 'rgba(54, 162, 235, 0.7)', // biru
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Refund',
                            data: @json($refundData),
                            backgroundColor: 'rgba(255, 99, 132, 0.7)', // merah
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Statistik Transaksi ({{ $modes[$mode] }})'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });

            // Donut chart total transaksi
            const ctxDonut = document.getElementById('donutChart').getContext('2d');
            new Chart(ctxDonut, {
                type: 'doughnut',
                data: {
                    labels: ['Transaksi Selesai', 'Refund Selesai'],
                    datasets: [{
                        label: 'Jumlah Transaksi',
                        data: [{{ $totalSelesai }}, {{ $totalRefund }}],
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(255, 99, 132, 0.7)'
                        ],
                        borderColor: [
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 99, 132, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        title: {
                            display: true,
                            text: 'Perbandingan Total Transaksi Selesai & Refund Selesai'
                        }
                    }
                }
            });
        </script>
    @endpush
</x-master-layout>
