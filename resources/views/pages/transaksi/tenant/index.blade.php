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
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <form method="GET" action="{{ route('transaksi.tenant') }}" class="mb-3">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="start_date">Dari Tanggal:</label>
                                <input type="date" id="start_date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                            </div>
                            <div class="col-md-4">
                                <label for="end_date">Sampai Tanggal:</label>
                                <input type="date" id="end_date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">Cari</button>
                                <a href="{{ route('export.transaksi.tenant', ['start_date' => request('start_date'), 'end_date' => request('end_date')]) }}" class="btn btn-success ms-2">Export CSV</a>
                            </div>
                        </div>
                    </form>
                    
                    <table class="table table-bordered text-center">
                        <thead>
                            <tr>
                                <th class="align-middle" rowspan="2">No</th>
                                <th class="align-middle" rowspan="2">Nama Tenant</th>
                                <th colspan="3">Transaksi Pesan Antar</th>
                                <th colspan="2">Transaksi Ambil Sendiri</th>
                                <th class="align-middle" rowspan="2">Rincian Penjualan</th>
                            </tr>
                            <tr>
                                <th>Pendapatan Kotor</th>
                                <th>Ongkir</th>
                                <th>Pendapatan Bersih</th>
                                <th>Pendapatan Kotor</th>
                                <th>Pendapatan Bersih</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transaksiTenant as $index => $p)
                            <tr>
                                <td>{{ $transaksiTenant->firstItem() + $index }}</td> <!-- Nomor berlanjut sesuai halaman -->
                                <td>{{ $p->nama_tenant }}</td>
                                <td>Rp{{ number_format($p->pendapatan_kotor_1, 0, ',', '.') }}</td> 
                                <td>Rp{{ number_format($p->total_ongkir, 0, ',', '.') }}</td> 
                                <td>Rp{{ number_format($p->pendapatan_bersih_1, 0, ',', '.') }}</td> 
                                <td>Rp{{ number_format($p->pendapatan_kotor_2, 0, ',', '.') }}</td> 
                                <td>Rp{{ number_format($p->pendapatan_bersih_2, 0, ',', '.') }}</td> 
                                <td><a href="{{ route('detail.transaksi.tenant', $p->id) }}" class="btn btn-info ms-2">Lihat</a></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <!-- Tambahkan navigasi pagination -->
                    <div class="d-flex justify-content-center mt-3">
                        {{ $transaksiTenant->links() }}
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-master-layout>
