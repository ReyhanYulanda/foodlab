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
                                <canvas id="totalChart" height="120"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    @push('js')
        <script>
            // Chart total transaksi
            const ctxTotal = document.getElementById('totalChart').getContext('2d');
            new Chart(ctxTotal, {
                type: 'bar',
                data: {
                    labels: ['Transaksi Selesai', 'Refund Selesai'],
                    datasets: [{
                        label: 'Jumlah Transaksi',
                        data: [{{ $totalSelesai }}, {{ $totalRefund }}],
                        backgroundColor: [
                            'rgba(54, 162, 235, 0.7)', // biru
                            'rgba(255, 99, 132, 0.7)' // merah
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
                        title: {
                            display: true,
                            text: 'Perbandingan Total Transaksi Selesai & Refund Selesai'
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
        </script>
    @endpush
</x-master-layout>
