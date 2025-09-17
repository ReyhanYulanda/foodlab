<x-master-layout>
    @push('cssLibrary')
        <link rel="stylesheet" href="{{ asset('') }}vendor/chart.js/Chart.min.css">
    @endpush

    <div class="main-content">
        <div class="title mb-4">
            <h3 class="fw-bold">Dashboard</h3>
            <p class="text-muted">Ringkasan transaksi dan aktivitas sistem</p>
        </div>

        {{-- Summary Section --}}
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm text-center p-3">
                    <h6 class="text-muted">Total Transaksi</h6>
                    <h4 class="text-primary fw-bold">{{ $totalTransaksi }}</h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm text-center p-3">
                    <h6 class="text-muted">Total Nominal</h6>
                    <h4 class="text-success fw-bold">Rp {{ number_format($totalNominal, 0, ',', '.') }}</h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm text-center p-3">
                    <h6 class="text-muted">Transaksi Bulan Ini</h6>
                    <h4 class="text-warning fw-bold">{{ $transaksiBulanIni }}</h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm text-center p-3">
                    <h6 class="text-muted">Refund Selesai</h6>
                    <h4 class="text-danger fw-bold">{{ array_sum($refund) }}</h4>
                </div>
            </div>
        </div>

        {{-- Chart Section --}}
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Perbandingan Transaksi & Refund</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="myChart" style="height: 320px;"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Distribusi Transaksi</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="myChart2" style="height: 320px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- Statistik Table & Pie --}}
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Statistik Browser</h5>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-striped table-hover table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Browser</th>
                                    <th>Pengguna</th>
                                    <th>Tren</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1</td>
                                    <td>Google Chrome</td>
                                    <td>5120</td>
                                    <td><i class="fa fa-caret-up text-success"></i></td>
                                </tr>
                                <tr>
                                    <td>2</td>
                                    <td>Mozilla Firefox</td>
                                    <td>4000</td>
                                    <td><i class="fa fa-caret-up text-success"></i></td>
                                </tr>
                                <tr>
                                    <td>3</td>
                                    <td>Safari</td>
                                    <td>8800</td>
                                    <td><i class="fa fa-caret-down text-danger"></i></td>
                                </tr>
                                <tr>
                                    <td>4</td>
                                    <td>Opera Mini</td>
                                    <td>4123</td>
                                    <td><i class="fa fa-caret-up text-success"></i></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Interest</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="myChart3" style="height: 320px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- Activity & Chat --}}
        <div class="row">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Aktivitas</h5>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y:auto;">
                        <ul class="timeline-xs">
                            <li class="timeline-item success">
                                <div class="margin-left-15">
                                    <div class="text-muted small">2 menit lalu</div>
                                    <p><a class="text-info" href="#">Bambang</a> menyelesaikan akun.</p>
                                </div>
                            </li>
                            <li class="timeline-item danger">
                                <div class="margin-left-15">
                                    <div class="text-muted small">11:11</div>
                                    <p>Completed new layout.</p>
                                </div>
                            </li>
                            <li class="timeline-item info">
                                <div class="margin-left-15">
                                    <div class="text-muted small">Kemarin</div>
                                    <p>Contacted <a class="text-info" href="#">Microsoft</a> for license upgrades.
                                    </p>
                                </div>
                            </li>
                            <li class="timeline-item warning">
                                <div class="margin-left-15">
                                    <div class="text-muted small">1 minggu lalu</div>
                                    <p>Server Maintenance.</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Chat --}}
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Chat</h5>
                    </div>
                    <div class="card-body small-padding">
                        <div class="panel-discussion ps-chat" style="max-height: 300px; overflow-y:auto;">
                            <ol class="discussion">
                                <li class="messages-date">Hari ini, 12:58</li>
                                <li class="self">
                                    <div class="message">
                                        <div class="message-name">Mas Bambang</div>
                                        <div class="message-text">Hi, Mba Inem</div>
                                    </div>
                                </li>
                                <li class="other">
                                    <div class="message">
                                        <div class="message-name">Mba Inem</div>
                                        <div class="message-text">Hi, i am good</div>
                                    </div>
                                </li>
                                <li class="self">
                                    <div class="message">
                                        <div class="message-name">Mas Bambang</div>
                                        <div class="message-text">Glad to see you ;)</div>
                                    </div>
                                </li>
                            </ol>
                        </div>
                        <div class="message-bar">
                            <div class="message-inner d-flex align-items-center">
                                <input class="form-control me-2" type="text" placeholder="Message">
                                <button class="btn btn-primary btn-sm">Send</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    @push('jsLibrary')
        <script src="{{ asset('') }}vendor/chart.js/Chart.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    @endpush

    @push('js')
        <script>
            // Chart Perbandingan
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
                            text: 'Transaksi vs Refund per Bulan'
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
