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
                            <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Cari nama user atau email user">
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
                            <th>Email</th>
                            <th>Jumlah Saldo</th>
                            <th>Action</th>
                        </thead>
                        <tbody>
                            @foreach ($saldos as $index => $saldo)
                                <tr>
                                    <td>{{ ($saldos->currentPage() - 1) * $saldos->perPage() + $loop->iteration }}</td>
                                    <td>{{ $saldo->user->name }}</td>
                                    <td>{{ $saldo->user->email }}</td>
                                    <td>{{ number_format($saldo->jumlah) }}</td>
                                    <td>
                                        <a href="{{ route('saldoKoin.riwayat', $saldo->user_id) }}" class="btn btn-info">Lihat Riwayat</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="form-group mb-0 d-flex align-items-center">
                            <label for="perPage" class="mr-2 mb-0">Tampilkan:</label>
                            <select class="form-control d-inline-block w-auto" id="perPage" onchange="window.location.href = this.value;">
                                @foreach ([10, 25, 50, 100] as $perPageOption)
                                    <option value="{{ request()->fullUrlWithQuery(['per_page' => $perPageOption]) }}" {{ (request('per_page', 10) == $perPageOption) ? 'selected' : '' }}>
                                        {{ $perPageOption }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="ml-2">data per halaman</span>
                        </div>

                        <div>
                            {{ $saldos->appends(request()->except('page'))->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-master-layout>
