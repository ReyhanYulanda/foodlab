<x-master-layout>
    <div class="main-content">
        <div class="title">
            Riwayat Transaksi Saldo Koin
        </div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>Riwayat Transaksi</h4>
                    <a href="{{ route('saldoKoin.index') }}" class="btn btn-secondary">Kembali</a>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Jumlah</th>
                                <th>Tipe</th>
                                <th>Deskripsi</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($transaksi as $index => $t)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ number_format($t->jumlah) }}</td>
                                <td>{{ ucfirst($t->tipe) }}</td>
                                <td>{{ $t->deskripsi }}</td>
                                <td>{{ $t->created_at->format('d-m-Y H:i') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-master-layout>
