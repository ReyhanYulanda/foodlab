<x-master-layout>
    <div class="main-content">
        <div class="title">
            Rincian Transaksi - {{ $driver->name }}
        </div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>Daftar Transaksi</h4>
                </div>
                <div class="card-body">
                    <a href="{{ url()->previous() }}" class="btn btn-secondary mb-3">Kembali</a>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kode Transaksi</th>
                                <th>Tanggal</th>
                                <th>Ongkos Kirim</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transaksi as $index => $trx)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>ORDER{{ $trx->id }}</td>
                                    <td>{{ $trx->created_at->format('d-m-Y H:i') }}</td>
                                    <td>Rp {{ number_format($trx->ongkos_kirim, 0, ',', '.') }}</td>

                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center">Tidak ada transaksi.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-master-layout>
