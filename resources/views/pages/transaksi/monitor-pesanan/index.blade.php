@extends('layouts.app')

@section('content')
    <div class="container">
        <h4 class="mb-3">Monitoring Pesanan</h4>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @elseif(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Pembeli</th>
                    <th>Tenant</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Catatan</th>
                    <th>Waktu</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transaksi as $trx)
                    <tr>
                        <td>#{{ $trx->id }}</td>
                        <td>{{ $trx->nama_pembeli }}</td>
                        <td>{{ $trx->nama_tenant }}</td>
                        <td><span class="badge bg-info">{{ $trx->status }}</span></td>
                        <td>Rp {{ number_format($trx->total, 0, ',', '.') }}</td>
                        <td>{{ $trx->catatan ?? '-' }}</td>
                        <td>{{ $trx->created_at->format('d-m-Y H:i') }}</td>
                        <td>
                            <form action="{{ route('monitor.pesanan.cancel', $trx->id) }}" method="POST"
                                onsubmit="return confirm('Yakin ingin membatalkan pesanan ini?');">
                                @csrf
                                <input type="text" name="catatan_penolakan" placeholder="Catatan penolakan"
                                    class="form-control mb-2" required>
                                <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center">Tidak ada pesanan</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{ $transaksi->links() }}
    </div>
@endsection
