<x-master-layout>
    <div class="main-content">
        <div class="title">
            Konfigurasi Saldo Koin
        </div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>Saldo Koin</h4>
                    <div class="row">
                        <div class="col-12">
                            @can('create saldo_koin')
                                <a class="btn btn-primary add" href="{{ route('saldoKoin.create') }}">Tambah Saldo</a>
                            @endcan
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('saldoKoin.index') }}" class="mb-3">
                        <div class="input-group">
                            <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Cari nama user">
                            <button class="btn btn-primary" type="submit">Cari</button>
                        </div>
                    </form>

                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    <table class="table table-responsive w-full">
                        <thead>
                            <th>No</th>
                            <th>Nama User</th>
                            <th>Jumlah Saldo</th>
                            <th>Action</th>
                        </thead>
                        <tbody>
                            @foreach ($saldos as $index => $saldo)
                                <tr>
                                    <td>{{ $saldos->firstItem() + $index }}</td>
                                    <td>{{ $saldo->user->name }}</td>
                                    <td>{{ number_format($saldo->jumlah) }}</td>
                                    <td>
                                        <a href="{{ route('saldoKoin.riwayat', $saldo->user_id) }}" class="btn btn-info">Lihat Riwayat</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="mt-3">
                        {{ $saldos->withQueryString()->links() }}
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</x-master-layout>
