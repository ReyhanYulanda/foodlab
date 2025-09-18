<x-master-layout>
    <div class="main-content">
        <div class="title">
            Monitor Voucher
        </div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>Daftar Pencatatan Voucher</h4>
                </div>
                <div class="card-body">
                    <table class="table table-responsive w-full table-striped">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama User</th>
                                <th>ID Transaksi</th>
                                <th>Voucher ID</th>
                                <th>Voucher Quantity</th>
                                <th>Referral Code</th>
                                <th>Cashback Amount</th>
                                <th>Tanggal Dicatat</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($catatVouchers as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $item->user->name ?? 'Tidak diketahui' }}</td>
                                    <td>{{ $item->transaksi_id }}</td>
                                    <td>{{ $item->voucher_id }}</td>
                                    <td>{{ $item->quantity_voucher ?? '-' }}</td>
                                    <td>{{ $item->voucher->cashback->referral_code ?? '-' }}</td>
                                    <td>{{ $item->cashback_amount ?? '-' }}</td>
                                    <td>{{ $item->created_at->format('d-m-Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center">Belum ada voucher yang dicatat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-master-layout>
