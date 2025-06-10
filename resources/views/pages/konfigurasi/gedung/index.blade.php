<x-master-layout>
    <div class="main-content">
        <div class="title">
            Konfigurasi
        </div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>Gedung</h4>
                    <div class="row">
                        <div class="col-12">
                            @can('create gedung')
                                <a class="btn btn-primary add" href="{{ route('gedung.create') }}">Tambah</a>
                            @endcan
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form action="{{ route('gedung.index') }}" method="GET" class="mb-3">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Cari nama gedung"
                                value="{{ request('search') }}">
                            <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                            <button class="btn btn-primary" type="submit">Cari</button>
                        </div>
                    </form>

                    <table class="table">
                        <thead>
                            <th>No</th>
                            <th>Nama Gedung</th>
                            <th>Action</th>
                        </thead>
                        <tbody>
                            @foreach ($gedung as $gdg)
                                <tr>
                                    <td>{{ ($gedung->currentPage() - 1) * $gedung->perPage() + $loop->iteration }}</td>
                                    <td>{{ $gdg->nama }}</td>
                                    <td>
                                        <a href="{{ route('gedung.edit', $gdg->id) }}"
                                            class="btn btn-secondary">Edit</a>
                                        <form action="{{ route('gedung.destroy', $gdg->id) }}" class="d-inline"
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
                            {{ $gedung->appends(request()->except('page'))->links() }}
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
