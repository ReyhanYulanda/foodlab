<x-master-layout>
    <div class="main-content">
        <section class="bg-white rounded p-10">
            <div class="flex flex-col gap-y-4">
                <div class="flex flex-col space-y-6 md:space-y-0 md:flex-row justify-between">
                    <div class="mr-6">
                        <h1 class="text-4xl font-semibold mb-2">Yuk Cek Hasil Penjualan Toko Kamu</h1>
                    </div>
                </div>
                <section class="grid md:grid-cols-2 xl:grid-cols-4 gap-6">
                    <div class="flex items-center p-8 bg-white shadow rounded-lg">
                        <div
                            class="inline-flex flex-shrink-0 items-center justify-center h-16 w-16 text-blue-600 bg-blue-100 rounded-full mr-6">
                            <svg aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                class="h-6 w-6">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div>
                            <span class="block text-gray-500">Total Pesanan Masuk</span>
                            <span class="block text-2xl font-bold">{{ $jumlahPesananMasuk }}</span>

                        </div>
                    </div>
                    <div class="flex items-center p-8 bg-white shadow rounded-lg">
                        <div
                            class="inline-flex flex-shrink-0 items-center justify-center h-16 w-16 text-green-600 bg-green-100 rounded-full mr-6">
                            <svg aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                class="h-6 w-6">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                        </div>
                        <div>
                            <span class="block text-gray-500">Total Pesanan Selesai</span>
                            <span class="block text-2xl font-bold">{{ $jumlahPesananSelesai }}</span>

                        </div>
                    </div>
                    <div class="flex items-center p-8 bg-white shadow rounded-lg">
                        <div
                            class="inline-flex flex-shrink-0 items-center justify-center h-16 w-16 text-red-600 bg-red-100 rounded-full mr-6">
                            <svg aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                class="h-6 w-6">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                            </svg>
                        </div>
                        <div>
                            <span class="block text-gray-500">Total Pesanan Dibatalkan</span>
                            <span class="block text-2xl font-bold">{{ $jumlahPesananDitolak }}</span>
                        </div>
                    </div>
                    <div class="flex items-center p-8 bg-white shadow rounded-lg">
                        <div>
                            <span class="block text-gray-500">Total Penjualan</span>
                            <span class="block text-2xl font-bold">Rp{{ number_format($totalKeuangan, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </section>
            </div>
        </section>

        <section class="flex justify-between w-full gap-10 bg-white rounded p-10">
            <!-- Chart widget -->
            <div
                class="flex flex-grow flex-col col-span-full shadow xl:col-span-8 bg-white rounded-sm border border-gray-200">
                <header class="px-5 py-4 border-b border-gray-100 flex items-center">
                    <h2 class="font-semibold text-gray-800">Penjualan Bulanan</h2>
                </header>

                <!-- Chart built with Chart.js 3 -->
                <div class="flex-grow">
                    <canvas id="analytics-card-01" width="800" height="300"></canvas>
                </div>
            </div>

            <!-- Menu paling sering dipesan -->
            <div class="flex flex-col bg-white shadow rounded-lg h-full w-[372px]">
                <div class="flex items-center justify-between px-6 py-5 font-semibold border-b border-gray-100">
                    <span>Menu Paling Sering Dipesan</span>
                    <!-- Refer here for full dropdown menu code: https://tailwindui.com/components/application-ui/elements/dropdowns -->
                </div>
                <div class="overflow-y-auto h-full">
                    <ul class="p-6 space-y-6">
                        @foreach ($listTransaksiDetail as $transaksi)
                            <li class="flex items-center">
                                <div class="h-10 w-10 mr-3 bg-gray-100 rounded-full overflow-hidden">
                                    <img src="{{asset($transaksi->gambar)}}">
                                </div>
                                <span class="text-gray-600">{{$transaksi->nama ?? $transaksi->nama_menu}}</span>
                                <span class="ml-auto font-semibold">{{$transaksi->total_pembelian}}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>

    </div>

    @push('jsLibrary')
        {{-- <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.min.js"
            integrity="sha512-L0Shl7nXXzIlBSUUPpxrokqq4ojqgZFQczTYlGjzONGTDAcLremjwaWv5A+EDLnxhQzY5xUZPWLOLqYRkY0Cbw=="
            crossorigin="anonymous" referrerpolicy="no-referrer"></script> --}}
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @endpush

    @push('js')
        <script>
            // Helper function to display thousands in K format const formatThousands=(value)=>
            // Intl.NumberFormat('en-US', {
            //     maximumSignificantDigits: 3,
            //     notation: 'compact',
            // }).format(10);

            // Define Chart.js default settings
            // Chart.defaults.font.family = '"Inter", sans-serif';
            // Chart.defaults.font.weight = '500';
            // Chart.defaults.color = 'rgb(148, 163, 184)';
            // Chart.defaults.scale.grid.color = 'rgb(241, 245, 249)';
            // Chart.defaults.plugins.tooltip.titleColor = 'rgb(30, 41, 59)';
            // Chart.defaults.plugins.tooltip.bodyColor = 'rgb(30, 41, 59)';
            // Chart.defaults.plugins.tooltip.backgroundColor = '#FFF';
            // Chart.defaults.plugins.tooltip.borderWidth = 1;
            // Chart.defaults.plugins.tooltip.borderColor = 'rgb(226, 232, 240)';
            // Chart.defaults.plugins.tooltip.displayColors = false;
            // Chart.defaults.plugins.tooltip.mode = 'nearest';
            // Chart.defaults.plugins.tooltip.intersect = false;
            // Chart.defaults.plugins.tooltip.position = 'nearest';
            // Chart.defaults.plugins.tooltip.caretSize = 0;
            // Chart.defaults.plugins.tooltip.caretPadding = 20;
            // Chart.defaults.plugins.tooltip.cornerRadius = 4;
            // Chart.defaults.plugins.tooltip.padding = 8;

            // A chart built with Chart.js 3
            // https://www.chartjs.org/
            const ctx = document.getElementById('analytics-card-01');
            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: {!! json_encode($grafik->pluck('nama_bulan')) !!},
                    datasets: [
                        // Indigo line
                        {
                            label: 'Current',
                            data: {!! json_encode($grafik->pluck('total_pesanan')) !!},
                            fill: true,
                            backgroundColor: 'rgba(59, 130, 246, 0.08)',
                            borderColor: 'rgb(99, 102, 241)',
                            borderWidth: 2,
                            tension: 0,
                            pointRadius: 0,
                            pointHoverRadius: 3,
                            pointBackgroundColor: 'rgb(99, 102, 241)',
                        },
                    ],
                },
                options: {
                    layout: {
                        padding: 20,
                    },
                    // scales: {
                    //     y: {
                    //         beginAtZero: true,
                    //         grid: {
                    //             drawBorder: false,
                    //         },
                    //         ticks: {
                    //             callback: (value) => formatThousands(value),
                    //         },
                    //     },
                    //     x: {
                    //         type: 'time',
                    //         time: {
                    //             parser: 'MM-DD-YYYY',
                    //             unit: 'month',
                    //             displayFormats: {
                    //                 month: 'MMM YY',
                    //             },
                    //         },
                    //         // grid: {
                    //         //     display: false,
                    //         //     drawBorder: false,
                    //         // },
                    //         // ticks: {
                    //         //     autoSkipPadding: 48,
                    //         //     maxRotation: 0,
                    //         // },
                    //     },
                    // },
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            callbacks: {
                                title: () => false, // Disable tooltip title
                                // label: (context) => formatThousands(context.parsed.y),
                            },
                        },
                    },
                    interaction: {
                        intersect: false,
                        mode: 'nearest',
                    },
                    maintainAspectRatio: false,
                },
            });
        </script>
    @endpush

</x-master-layout>
