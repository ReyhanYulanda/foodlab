<x-master-layout>
    <div class="main-content">
        <div class="title">
            Transaksi Tenant
        </div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>Detail Transaksi</h4>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <form action="{{ route('detail.transaksi.tenant', ['id' => request()->route('id')]) }}" method="GET" class="mb-3">
                        <div class="row g-2">
                            <div class="col-md-2">
                                <label for="search_tanggal" class="form-label visually-hidden">Tanggal</label>
                                <input type="date" name="search_tanggal" id="search_tanggal" class="form-control" placeholder="Tanggal" value="{{ request('search_tanggal') }}">
                            </div>
                            <div class="col-md-3">
                                <label for="search_keyword" class="form-label visually-hidden">Keyword</label>
                                <input type="text" name="search_keyword" id="search_keyword" class="form-control" placeholder="No. Pesanan, Nama Pemesan/Pengantar"
                                    value="{{ request('search_keyword') }}">
                            </div>
                            <div class="col-md-2">
                                <label for="status_pemesan" class="form-label visually-hidden">Status Pemesan</label>
                                <select name="status_pemesan" id="status_pemesan" class="form-control">
                                    <option value="">Semua Status Pemesan</option>
                                    <option value="antar" {{ (request('status_pemesan') == 'antar') ? 'selected' : '' }}>Pesan Antar</option>
                                    <option value="sendiri" {{ (request('status_pemesan') == 'sendiri') ? 'selected' : '' }}>Ambil Sendiri</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <button class="btn btn-primary w-100" type="submit">Cari</button>
                            </div>
                        </div>
                        <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
                    </form>

                    <table class="table table-responsive w-full table-striped">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Waktu</th>
                                <th>No Pesanan</th>
                                <th>Nama Pemesan</th>
                                <th>Nama Pengantar</th>
                                <th>Status Pemesan</th>
                                <th>List Pesanan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transaksiDetails as $key => $detail)
                                <tr>
                                    <td>{{ ($transaksiDetails->currentPage() - 1) * $transaksiDetails->perPage() + $loop->iteration }}</td>
                                    <td>{{ $detail->created_at->format('d-m-Y') }}</td>
                                    <td>{{ $detail->created_at->format('H:i:s') }}</td> 
                                    <td>{{ $detail->id }}</td>
                                    <td>{{ $detail->user->name ?? '-' }}</td> 
                                    <td>{{ $detail->driver->name ?? '-' }}</td> 
                                    <td>
                                        {{ $detail->isAntar == 1 ? 'Pesan Antar' : 'Ambil Sendiri' }}
                                    </td>
                                    <td>
                                        <button 
                                            class="btn btn-info ms-2"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#pesananModal"
                                            onclick="getPesanan({{ $detail->id }})"
                                        >
                                            Lihat Pesanan
                                        </button>
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
                            {{ $transaksiDetails->appends(request()->except('page'))->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
        
    <div class="modal fade" id="pesananModal" tabindex="-1" aria-labelledby="pesananModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Pesanan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Nama Menu</th>
                                <th>Jumlah</th>
                                <th>Harga</th>
                            </tr>
                        </thead>
                        <tbody id="tablePesananBody">
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function getPesanan(transaksiId) {
            fetch(`/pesanan-transaksi/${transaksiId}`)
                .then(response => response.json())
                .then(data => {
                    const tbody = document.getElementById('tablePesananBody');
                    tbody.innerHTML = '';
        
                    data.forEach(pesanan => {
                        const row = `
                            <tr>
                                <td>${pesanan.nama_menu}</td>
                                <td>${pesanan.jumlah}</td>
                                <td>Rp ${new Intl.NumberFormat('id-ID').format(pesanan.harga)}</td>
                            </tr>
                        `;
                        tbody.innerHTML += row;
                    });
                })
                .catch(error => {
                    alert("Gagal memuat data pesanan.");
                    console.error(error);
                });
        }
        </script>        
</x-master-layout>
