<x-master-layout>
    <div class="main-content">
        <div class="title">
            Konfigurasi
        </div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>Ruangan</h4>
                    <div class="row">
                        <div class="col-12 d-flex justify-content-between align-items-center">
                            @can('create ruangan')
                                <a class="btn btn-primary add" href="{{ route('ruangan.create') }}">Tambah</a>
                            @endcan
                            {{-- Dropdown per_page dipindahkan ke bawah --}}
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form action="{{ route('ruangan.index') }}" method="GET" class="mb-3">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Cari ruangan, kode, atau gedung"
                                value="{{ request('search') }}">
                            <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                            <button class="btn btn-primary" type="submit">Cari</button>
                        </div>
                    </form>
                    <table class="table">
                        <thead>
                            <th>No</th>
                            <th>Nama Ruangan</th>
                            <th>Kode Ruangan</th>
                            <th>Gedung</th>
                            <th>Action</th>
                        </thead>
                        <tbody>
                            @foreach ($ruangan as $ruang)
                                <tr>
                                    <td>{{ ($ruangan->currentPage() - 1) * $ruangan->perPage() + $loop->iteration }}</td>
                                    <td>{{ $ruang->nama }}</td>
                                    <td>{{ $ruang->kode_ruangan }}</td>
                                    <td>{{ $ruang->gedung->nama ?? '-' }}</td>
                                    <td>
                                        <a href="{{ route('ruangan.edit', $ruang->id) }}"
                                            class="btn btn-secondary">Edit</a>
                                        <form action="{{ route('ruangan.destroy', $ruang->id) }}" class="d-inline"
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

                    {{-- Kontainer baru untuk dropdown dan pagination di kiri bawah --}}
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        {{-- Dropdown untuk memilih jumlah data per halaman --}}
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

                        {{-- Menampilkan pagination links (tetap di kanan) --}}
                        <div>
                            {{ $ruangan->appends(request()->except('page'))->links() }}
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