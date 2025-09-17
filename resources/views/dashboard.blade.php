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
        </div>
    </div>

    @push('jsLibrary')
        <script src="{{ asset('vendor/chart.js/Chart.min.js') }}"></script>
        <script>
            // Chart Bulanan (row atas, contoh dummy aja)
            new Chart(document.getElementById('chartBulanan'), {
                type: 'bar',
                data: {
                    labels: @json($labels),
                    datasets: [{
                        label: 'Transaksi Bulanan',
                        data: @json($selesaiData), // sementara isi dari selesaiData biar ada isi
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    }]
                }
            });

            // Chart Compare (row bawah, 2 bar bersampingan)
            new Chart(document.getElementById('chartCompare'), {
                type: 'bar',
                data: {
                    labels: @json($labels),
                    datasets: [{
                            label: 'Selesai',
                            data: @json($selesaiData),
                            backgroundColor: 'rgba(54, 162, 235, 0.7)'
                        },
                        {
                            label: 'Refund',
                            data: @json($refundData),
                            backgroundColor: 'rgba(255, 99, 132, 0.7)'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            stacked: false
                        },
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        </script>
    @endpush
</x-master-layout>
