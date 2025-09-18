<x-master-layout>
    <div class="main-content">
        <div class="title">
            Monitor Voucher
        </div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>Daftar Transaksi dengan Cashback</h4>
                </div>
                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <table class="table table-responsive w-full table-striped">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama User</th>
                                <th>Voucher ID</th>
                                <th>Voucher Quantity</th>
                                <th>Referral Code</th>
                                <th>Cashback Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transaksis as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $item->user->name ?? 'Tidak diketahui' }}</td>
                                    <td>{{ $item->voucher_id ?? '-' }}</td>
                                    <td>{{ $item->voucher->quantity ?? '-' }}</td>
                                    <td>{{ $item->voucher->cashback->referral_code ?? '-' }}</td>
                                    <td>{{ number_format($item->cashback_amount, 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center">Tidak ada transaksi dengan cashback.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-master-layout>
