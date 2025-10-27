<x-master-layout>
    <div class="main-content">
        <div class="title">Monitoring Pesanan</div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>Total Pesanan Aktif: {{ $transaksi->total() }}</h4>
                </div>
                <div class="card-body">

                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>ID Transaksi</th>
                                <th>Kode Pemesanan</th>
                                <th>Pembeli</th>
                                <th>Tenant</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th>Waktu</th>
                                <th>Catatan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($transaksi as $index => $trx)
                                <tr>
                                    <td>{{ $transaksi->firstItem() + $index }}</td>
                                    <td>#{{ $trx->id }}</td>
                                    <td>{{ $trx->kode_pemesanan ?? '-' }}</td>
                                    <td>{{ $trx->nama_pembeli ?? '-' }}</td>
                                    <td>{{ $trx->nama_tenant ?? '-' }}</td>
                                    <td>
                                        <span class="badge bg-info">{{ $trx->status }}</span>
                                    </td>
                                    <td>{{ number_format($trx->total, 0, ',', '.') }}</td>
                                    <td>{{ $trx->created_at->format('d-m-Y H:i') }}</td>
                                    <td>{{ $trx->catatan ?? '-' }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('monitor.pesanan.cancel', $trx->id) }}"
                                            onsubmit="return confirm('Yakin ingin membatalkan pesanan ini?');">
                                            @csrf
                                            <input type="text" name="catatan_penolakan"
                                                placeholder="Catatan penolakan" class="form-control mb-2" required>
                                            <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center">Tidak ada pesanan</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="mt-3">
                        {{ $transaksi->links() }}
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-master-layout>
