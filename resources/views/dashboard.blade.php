<x-master-layout>
    @push('cssLibrary')
        <link rel="stylesheet" href="{{ asset('vendor/chart.js/Chart.min.css') }}">
    @endpush

    <div class="main-content">
        <div class="title">Dashboard</div>
        <div class="content-wrapper">
            <div class="row same-height">
                {{-- Statistik Bulanan --}}
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h4>Statistik Bulanan</h4>
                        </div>
                        <div class="card-body">
                            <canvas id="myChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Statistik Status --}}
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h4>Statistik</h4>
                        </div>
                        <div class="card-body">
                            <div class="progress-wrapper mb-3">
                                <h5>Pesanan Selesai</h5>
                                <div class="progress progress-bar-small">
                                    <div class="progress-bar bg-success"
                                        style="width: {{ $totalTransaksi > 0 ? ($pesananSelesai / $totalTransaksi) * 100 : 0 }}%">
                                    </div>
                                </div>
                                <small>{{ $pesananSelesai }} dari {{ $totalTransaksi }}</small>
                            </div>
                            <div class="progress-wrapper mb-3">
                                <h5>Pesanan Refund</h5>
                                <div class="progress progress-bar-small">
                                    <div class="progress-bar bg-danger"
                                        style="width: {{ $totalTransaksi > 0 ? ($pesananRefund / $totalTransaksi) * 100 : 0 }}%">
                                    </div>
                                </div>
                                <small>{{ $pesananRefund }} dari {{ $totalTransaksi }}</small>
                            </div>

                            <canvas id="myChart2"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('jsLibrary')
        <script src="{{ asset('vendor/chart.js/Chart.min.js') }}"></script>
        <script>
            // Data grafik bulanan
            const bulanLabels = @json($bulanLabels);
            const transaksiPerBulan = @json($transaksiPerBulan);

            new Chart(document.getElementById('myChart'), {
                type: 'bar',
                data: {
                    labels: bulanLabels,
                    datasets: [{
                        label: 'Jumlah Transaksi',
                        data: transaksiPerBulan,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    }]
                }
            });

            // Data grafik status
            new Chart(document.getElementById('myChart2'), {
                type: 'doughnut',
                data: {
                    labels: ['Selesai', 'Refund'],
                    datasets: [{
                        data: [{{ $pesananSelesai }}, {{ $pesananRefund }}],
                        backgroundColor: ['#28a745', '#dc3545']
                    }]
                }
            });
        </script>
    @endpush
</x-master-layout>
