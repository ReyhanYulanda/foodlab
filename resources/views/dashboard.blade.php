<x-master-layout>
    @push('cssLibrary')
        <link rel="stylesheet" href="{{ asset('') }}vendor/chart.js/Chart.min.css">
    @endpush
    <div class="main-content">
        <div class="title">
            Dashboard
        </div>
        <div class="content-wrapper">
            <div class="row same-height">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h4>Monthly Sales</h4>
                        </div>
                        <div class="card-body">
                            <canvas id="myChart" height="642" width="1388"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h4>Statistics</h4>
                        </div>
                        <div class="card-body">
                            <div class="progress-wrapper">
                                <h4>Pesanan Selesai</h4>
                                <div class="progress progress-bar-small">
                                    <div class="progress-bar progress-bar-small" style="width: 25%" role="progressbar"
                                        aria-valuenow="10" aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                            </div>
                            <div class="progress-wrapper">
                                <h4>Pesanan Refund</h4>
                                <div class="progress progress-bar-small">
                                    <div class="progress-bar progress-bar-small bg-pink" style="width: 45%"
                                        role="progressbar" aria-valuenow="10" aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                            </div>
                            <canvas id="myChart2" height="842" width="1388"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        

    </div>
    @push('jsLibrary')
        <script src="{{ asset('') }}vendor/chart.js/Chart.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
        <script src="{{ asset('') }}assets/js/pages/index.min.js"></script>
    @endpush

    @push('js')
        <script>
            const ctx = document.getElementById('myChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: @json($labels),
                    datasets: [{
                            label: 'Transaksi Selesai',
                            data: @json($selesai),
                            borderColor: 'rgba(75, 192, 192, 1)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'Refund Selesai',
                            data: @json($refund),
                            borderColor: 'rgba(255, 99, 132, 1)',
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            tension: 0.3,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Perbandingan Transaksi Selesai & Refund per Bulan'
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
