<x-master-layout>
    <div class="main-content">
        <div class="title">
            Konfigurasi
        </div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>tenant</h4>
                    <div class="row">
                        <div class="col-12">
                            @can('create tenant')
                                <a class="btn btn-primary add" href="{{ route('tenant.create') }}">Tambah</a>
                            @endcan
                        </div>
                    </div>
                </div>
                <div class="card-body table-responsive">
                    <form action="{{ route('tenant.index') }}" method="GET" class="mb-3">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Cari nama tenant, kavling, pemilik, no. rek toko/pribadi"
                                value="{{ request('search') }}">
                            <button class="btn btn-primary" type="submit">Cari</button>
                        </div>
                    </form>

                    <table class="table table-striped">
                        <thead>
                            <th>No</th>
                            <th>Nama Tenant</th>
                            <th>Kavling</th>
                            <th>Jam Buka</th>
                            <th>Jam Tutup</th>
                            <th>Pemilik</th>
                            <th>No. Telepon</th> 
                            <th>No. Rekening Toko</th> 
                            <th>No. Rekening Pribadi</th> 
                            <th>Gambar</th>
                            <th>Status Toko</th>
                            <th>Action</th>
                        </thead>
                        <tbody>
                            @foreach ($tenants as $tenant)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $tenant->nama_tenant }}</td>
                                    <td>{{ $tenant->nama_kavling ?? '-' }}</td>
                                    <td>{{ $tenant->jam_buka }}</td>
                                    <td>{{ $tenant->jam_tutup }}</td>
                                    <td>{{ @$tenant->pemilik->name }}</td>
                                    <td>{{ @$tenant->pemilik->phone ?? '-' }}</td> 
                                    <td>{{ $tenant->no_rekening_toko ?? '-' }}</td> 
                                    <td>{{ $tenant->no_rekening_pribadi ?? '-' }}</td> 
                                    <td>
                                        <a href="#" data-bs-toggle="modal" data-bs-target="#imageModal{{ $loop->index }}">
                                            <img src="{{ $tenant->gambar }}" alt="Gambar Tenant" class="img-fluid" width="200px">
                                        </a>
                                    </td>
                                    <td>
                                        @if ($tenant->pemilik->isOnline)
                                            <span class="badge bg-success">Buka</span>
                                        @else
                                            <span class="badge bg-danger">Tutup</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('tenant.edit', $tenant->id) }}"
                                            class="btn btn-secondary">Edit</a>
                                        <form action="{{ route('tenant.destroy', $tenant->id) }}" class="d-inline"
                                            method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </form>
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
                            {{ $tenants->appends(request()->except('page'))->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @foreach ($tenants as $tenant)
        <div class="modal fade" id="imageModal{{ $loop->index }}" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Gambar Tenant</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img src="{{ $tenant->gambar }}" alt="Gambar Tenant" class="img-fluid">
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    @push('js')
        @if ($errors->any())
            @foreach ($errors->all() as $error)
                toastr.error('{{ $error }}', 'Error');
            @endforeach
        @endif
    @endpush

</x-master-layout>