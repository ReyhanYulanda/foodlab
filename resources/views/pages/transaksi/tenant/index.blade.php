<x-master-layout>
    <div class="main-content">
        <div class="title">
            Transaksi
        </div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>Transaksi Tenant</h4>
                    <div class="row">
                    </div>
                </div>
                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <div class="alert alert-info">
                        <strong>Informasi:</strong> Perhitungan transaksi harian dimulai dari pukul
                        <strong>06:00</strong> hari sebelumnya hingga <strong>05:59</strong> pada hari yang dipilih.
                        <br>Contoh: Jika Anda memilih tanggal <strong>17 Juni</strong>, maka data yang ditampilkan
                        berasal dari <strong>16 Juni pukul 06:00</strong> sampai <strong>17 Juni pukul 05:59</strong>.
                    </div>

                    <form method="GET" action="{{ route('transaksi.tenant') }}" class="mb-3">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="filter_date">Pilih Tanggal (Per Hari):</label>
                                <input type="date" id="filter_date" name="filter_date" class="form-control"
                                    value="{{ request('filter_date') }}">
                            </div>
                            <div class="col-md-4">
                                <label for="start_date">Dari Tanggal:</label>
                                <input type="date" id="start_date" name="start_date" class="form-control"
                                    value="{{ request('start_date') }}">
                            </div>
                            <div class="col-md-4">
                                <label for="end_date">Sampai Tanggal:</label>
                                <input type="date" id="end_date" name="end_date" class="form-control"
                                    value="{{ request('end_date') }}">
                            </div>
                            <div class="col-md-12 d-flex justify-content-start mt-3">
                                <button type="submit" class="btn btn-primary">Cari</button>
                                <a href="{{ route('export.transaksi.tenant', [
                                    'filter_date' => request('filter_date'),
                                    'start_date' => request('start_date'),
                                    'end_date' => request('end_date'),
                                ]) }}"
                                    class="btn btn-success ms-2">Export CSV 90%</a>

                                <a href="{{ route('export.transaksi.tenant.jasa', [
                                    'filter_date' => request('filter_date'),
                                    'start_date' => request('start_date'),
                                    'end_date' => request('end_date'),
                                ]) }}"
                                    class="btn btn-success ms-2">Export CSV 10%</a>
                                    
                                <a href="{{ route('export.transaksi.tenant.rekap', [
                                    'filter_date' => request('filter_date'),
                                    'start_date' => request('start_date'),
                                    'end_date' => request('end_date'),
                                ]) }}"
                                    class="btn btn-success ms-2">Rekap Tenant</a>
                            </div>
                        </div>
                    </form>

                    <table class="table table-bordered text-center table-striped">
                        <thead class="bg-white">
                            <tr>
                                <th class="align-middle" rowspan="2">No</th>
                                <th class="align-middle" rowspan="2">Nama Tenant</th>
                                <th colspan="2">Transaksi Pesan Antar</th>
                                <th colspan="2">Transaksi Ambil Sendiri</th>
                                <th class="align-middle" rowspan="2">Rincian Penjualan</th>
                            </tr>
                            <tr>
                                <th>Pendapatan Kotor</th>
                                <th>Pendapatan Bersih</th>
                                <th>Pendapatan Kotor</th>
                                <th>Pendapatan Bersih</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse  ($transaksiTenant as $index => $p)
                                <tr>
                                    <td>{{ $transaksiTenant->firstItem() + $index }}</td>
                                    <td>{{ $p->nama_tenant }}</td>
                                    <td>Rp{{ number_format($p->pendapatan_kotor_1, 0, ',', '.') }}</td>
                                    <td>Rp{{ number_format($p->pendapatan_bersih_1, 0, ',', '.') }}</td>
                                    <td>Rp{{ number_format($p->pendapatan_kotor_2, 0, ',', '.') }}</td>
                                    <td>Rp{{ number_format($p->pendapatan_bersih_2, 0, ',', '.') }}</td>
                                    <td>
                                        <a href="{{ route('detail.transaksi.tenant', ['id' => $p->id, 'filter_date' => request('filter_date')]) }}"
                                            class="btn btn-primary">
                                            Lihat Detail
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">Data tidak tersedia untuk hari
                                        ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="form-group mb-0 d-flex align-items-center">
                            <label for="perPage" class="mr-2 mb-0">Tampilkan:</label>
                            <select class="form-control d-inline-block w-auto" id="perPage"
                                onchange="window.location.href = this.value;">
                                @foreach ([10, 25, 50, 100] as $perPageOption)
                                    <option value="{{ request()->fullUrlWithQuery(['per_page' => $perPageOption]) }}"
                                        {{ request('per_page', 10) == $perPageOption ? 'selected' : '' }}>
                                        {{ $perPageOption }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="ml-2">data per halaman</span>
                        </div>

                        <div>
                            {{ $transaksiTenant->appends(request()->except('page'))->links() }}
                        </div>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', () => {
                            const filterDate = document.getElementById('filter_date');
                            const startDate = document.getElementById('start_date');
                            const endDate = document.getElementById('end_date');

                            function toggleFilterDate() {
                                if (startDate.value || endDate.value) {
                                    filterDate.disabled = true;
                                } else {
                                    filterDate.disabled = false;
                                }
                            }

                            startDate.addEventListener('input', toggleFilterDate);
                            endDate.addEventListener('input', toggleFilterDate);
                            toggleFilterDate();
                        });
                    </script>
                </div>
            </div>
        </div>
    </div>
</x-master-layout>
