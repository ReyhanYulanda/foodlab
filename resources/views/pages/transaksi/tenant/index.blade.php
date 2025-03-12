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
                                <th>Pendapatan Kotor 1</th>
                                <th>Ongkir</th>
                                <th>Pendapatan Bersih</th>
                                <th>Pendapatan Kotor 2</th>
                                <th>Pendapatan Bersih</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pendapatan as $index => $p)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $p->nama_tenant }}</td>
                                <td>Rp{{ number_format($p->pendapatan_kotor_1, 0, ',', '.') }}</td> 
                                <td>Rp{{ number_format($p->total_ongkir, 0, ',', '.') }}</td> 
                                <td>Rp{{ number_format($p->pendapatan_bersih_1, 0, ',', '.') }}</td> 
                                <td>Rp{{ number_format($p->pendapatan_kotor_2, 0, ',', '.') }}</td> 
                                <td>Rp{{ number_format($p->pendapatan_bersih_2, 0, ',', '.') }}</td> 
                                <td><a href="#">Lihat</a></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-master-layout>
