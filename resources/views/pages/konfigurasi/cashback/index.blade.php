<x-master-layout>
    <div class="main-content">
        <div class="title">
            Konfigurasi
        </div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>Cashback</h4>
                    <div class="row">
                        <div class="col-12">
                            @can('create cashback')
                                <a class="btn btn-primary add" href="{{ route('cashback.create') }}">Tambah</a>
                            @endcan
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form action="{{ route('cashback.index') }}" method="GET" class="mb-3">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Cari referral code"
                                value="{{ request('search') }}">
                            <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                            <button class="btn btn-primary" type="submit">Cari</button>
                        </div>
                    </form>

                    <table class="table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Value (%)</th>
                                <th>Quantity</th>
                                <th>Is Valid</th>
                                <th>Referral Code</th>
                                <th>Minimal Pembelian</th>
                                <th>Maximum Cashback</th>
                                <th>Max Used</th>
                                <th>Periode</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cashback as $cbc)
                                <tr>
                                    <td>{{ ($cashback->currentPage() - 1) * $cashback->perPage() + $loop->iteration }}
                                    </td>
                                    <td>{{ $cbc->value * 100 }}%</td>
                                    <td>{{ $cbc->quantity }}</td>
                                    <td>
                                        @if ($cbc->is_valid)
                                            <span class="badge bg-success">Aktif</span>
                                        @else
                                            <span class="badge bg-danger">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td>{{ $cbc->referral_code }}</td>
                                    <td>{{ number_format($cbc->minimal_beli, 0, ',', '.') }}</td>
                                    <td>{{ number_format($cbc->max_cashback, 0, ',', '.') }}</td>
                                    <td>{{ $cbc->max_used }}</td>
                                    <td>
                                        @php
                                            $now = \Carbon\Carbon::now();
                                            $start = \Carbon\Carbon::parse($cbc->start_date);
                                            $end = \Carbon\Carbon::parse($cbc->end_date);
                                        @endphp

                                        {{ $start->format('d/m/Y') }} - {{ $end->format('d/m/Y') }}
                                        <br>
                                        @if ($now->lt($start))
                                            <span class="badge bg-secondary">Belum Aktif</span>
                                        @elseif ($now->between($start, $end))
                                            <span class="badge bg-success">Berlaku</span>
                                        @else
                                            <span class="badge bg-danger">Expired</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('cashback.edit', $cbc->id) }}"
                                            class="btn btn-secondary btn-sm">Edit</a>
                                        <form action="{{ route('cashback.destroy', $cbc->id) }}" class="d-inline"
                                            method="POST"
                                            onsubmit="return confirm('Yakin ingin menghapus cashback ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="form-group mb-0 d-flex align-items-center">
                            <label for="perPage" class="mr-2 mb-0">Tampilkan:</label>
                            <select class="form-control d-inline-block w-auto" id="perPage"
                                onchange="window.location.href = this.value;">
                                @foreach ([10, 25, 50, 100] as $perPageOption)
                                    <option value="{{ request()->fullUrlWithQuery(['per_page' => $perPageOption]) }}"
                                        {{ request('per_page', 10) == $perPageOption ? 'selected' : '' }}>
                                        {{ $perPageOption }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="ml-2">data per halaman</span>
                        </div>

                        <div>
                            {{ $cashback->appends(request()->except('page'))->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('js')
        @if ($errors->any())
            @foreach ($errors->all() as $error)
                toastr.error('{{ $error }}', 'Error');
            @endforeach
        @endif
    @endpush

</x-master-layout>
