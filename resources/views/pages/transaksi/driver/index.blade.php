<x-master-layout>
    <div class="main-content">
        <div class="title">
            Rekap Pendapatan Driver
        </div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>Pendapatan Kotor & Bersih</h4>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('transaksi.driver') }}" class="mb-4 flex gap-2">
                        <input type="date" name="start_date" id="start_date" value="{{ request('start_date') }}"
                            class="form-control w-48">
                        <input type="date" name="end_date" id="end_date" value="{{ request('end_date') }}"
                            class="form-control w-48">
                        <button type="submit" id="filter_date" class="btn btn-primary">Filter</button>
                        <a href="{{ route('transaksi.driver') }}" class="btn btn-secondary">Reset</a>
                    </form>

                    <table class="table table-responsive w-full table-striped">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Driver</th>
                                <th>Pendapatan Pens (10%)</th>
                                <th>Pendapatan Driver (90%)</th>
                                <th>Saldo Driver</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $item->driver->name ?? 'Tidak diketahui' }}</td>
                                    <td>Rp {{ number_format($item->pendapatan_pens, 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format($item->pendapatan_driver, 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format($item->saldo_driver, 0, ',', '.') }}</td>
                                    <td>
                                        <a href="{{ route('detail.transaksi.driver', ['id' => $item->driver_id, 'start_date' => request('start_date'), 'end_date' => request('end_date')]) }}"
                                            class="btn btn-primary btn-sm">
                                            Rincian
                                        </a>
                                    </td>

                                    {{-- <td>
                                        <a href="{{ route('detail.pencairan.transaksi.driver', $item->driver_id) }}"
                                            class="btn btn-primary btn-sm">
                                            Cairkan
                                        </a>
                                    </td> --}}
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center">Tidak ada data driver.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const filterDate = document.getElementById('filter_date');
                const startDate = document.getElementById('start_date');
                const endDate = document.getElementById('end_date');

                function toggleFilterDate() {
                    if (startDate.value || endDate.value) {
                        filterDate.disabled = false; // biar bisa klik filter
                    } else {
                        filterDate.disabled = true;
                    }
                }

                startDate.addEventListener('input', toggleFilterDate);
                endDate.addEventListener('input', toggleFilterDate);
                toggleFilterDate();
            });
        </script>
    </div>
</x-master-layout>
