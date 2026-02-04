<x-master-layout>
    <div class="main-content">
        <div class="title">
            Status Pesanan Transaksi Tenant
        </div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>Detail Transaksi</h4>
                </div>
                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <form method="GET" action="{{ route('status.pesanan.transaksi.tenant') }}" class="mb-3">
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <label for="search">Pencarian Umum:</label>
                                <input type="text" name="search" class="form-control"
                                    placeholder="ID, Pembeli, Tenant, Pengantar" value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2">
                                <label for="filter_date">Tanggal:</label>
                                <input type="date" id="filter_date" name="filter_date" class="form-control"
                                    value="{{ request('filter_date') }}">
                            </div>
                            <div class="col-md-2">
                                <label for="start_date">Dari Tanggal:</label>
                                <input type="date" id="start_date" name="start_date" class="form-control"
                                    value="{{ request('start_date') }}">
                            </div>
                            <div class="col-md-2">
                                <label for="end_date">Sampai Tanggal:</label>
                                <input type="date" id="end_date" name="end_date" class="form-control"
                                    value="{{ request('end_date') }}">
                            </div>
                            <div class="col-md-2">
                                <label for="status">Status Transaksi:</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="">-- Semua --</option>
                                    @foreach (['pesanan_masuk', 'pesanan_ditolak', 'pesanan_diproses', 'siap_diantar', 'siap_diambil', 'diantar', 'selesai', 'refund_selesai'] as $status)
                                        <option value="{{ $status }}"
                                            {{ request('status') == $status ? 'selected' : '' }}>
                                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label for="isAntar">Metode Pengantaran:</label>
                                <select name="isAntar" id="isAntar" class="form-control">
                                    <option value="">-- Semua --</option>
                                    <option value="1" {{ request('isAntar') == '1' ? 'selected' : '' }}>Pesan Antar
                                    </option>
                                    <option value="0" {{ request('isAntar') == '0' ? 'selected' : '' }}>Ambil
                                        Sendiri</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-primary w-100">Cari</button>
                            </div>
                        </div>
                    </form>


                    <table class="table table-responsive w-full table-striped">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>ID Transaksi</th>
                                <th>Waktu Transaksi</th>
                                <th>Status Transaksi</th>
                                <th>Nama Tenant</th>
                                <th>Nama Pembeli</th>
                                <th>Nama Pengantar</th>
                                <th>Nama Ruangan</th>
                                <th>Metode Pengantaran</th>
                                <th>List Pesanan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($statusTransaksi as $key)
                                <tr>
                                    <td>{{ ($statusTransaksi->currentPage() - 1) * $statusTransaksi->perPage() + $loop->iteration }}
                                    </td>
                                    <td>{{ $key->id }}</td>
                                    <td>{{ \Carbon\Carbon::parse($key->updated_at)->format('H:i:s d-m-Y') }}</td>
                                    <td>{{ $key->status }}</td>
                                    <td>{{ $key->nama_tenant ?? '-' }}</td>
                                    <td>{{ $key->nama_pembeli ?? '-' }}</td>
                                    <td>{{ $key->driver->name ?? '-' }}</td>
                                    <td>{{ $key->ruangan->nama_ruangan ?? '-' }}</td>
                                    <td>
                                        {{ $key->isAntar == 1 ? 'Pesan Antar' : 'Ambil Sendiri' }}
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                            data-bs-target="#pesananModal" onclick="getPesanan({{ $key->id }})">
                                            Lihat
                                        </button>
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
                            {{ $statusTransaksi->appends(request()->except('page'))->links() }}
                        </div>
                    </div>
                </div>
                <div class="modal fade" id="pesananModal" tabindex="-1" aria-labelledby="pesananModalLabel"
                    aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Detail Pesanan</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                    aria-label="Close"></button>
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

                                <div class="mt-3">
                                    <strong>Catatan Lokasi Pengantaran:</strong>
                                    <p id="catatanLokasi"></p>
                                    <strong>Catatan Penolakan:</strong>
                                    <p id="catatanPenolakan"></p>
                                    <strong>Bukti Pengantaran:</strong>
                                    <div id="buktiPengantaranContainer" class="mt-2">
                                        <img id="buktiPengantaranImg" src="" alt="Bukti Pengantaran"
                                            class="img-fluid rounded shadow-sm"
                                            style="max-width: 300px; display: none;">
                                        <p id="buktiPengantaranText" class="text-muted fst-italic"
                                            style="display: none;">Belum ada bukti pengantaran</p>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary"
                                    data-bs-dismiss="modal">Tutup</button>
                            </div>
                        </div>
                    </div>
                </div>
                <script>
                    function getPesanan(transaksiId) {
                        fetch(`/pesanan-transaksi/${transaksiId}`)
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('HTTP error! Status: ' + response.status);
                                }
                                return response.json();
                            })
                            .then(data => {
                                const tbody = document.getElementById('tablePesananBody');
                                const lokasiEl = document.getElementById('catatanLokasi');
                                const penolakanEl = document.getElementById('catatanPenolakan');
                                const buktiImg = document.getElementById('buktiPengantaranImg');
                                const buktiText = document.getElementById('buktiPengantaranText');

                                tbody.innerHTML = '';
                                data.pesanan.forEach(pesanan => {
                                    const row = `
                                        <tr>
                                            <td>${pesanan.nama_menu}</td>
                                            <td>${pesanan.jumlah}</td>
                                            <td>Rp ${new Intl.NumberFormat('id-ID').format(pesanan.harga)}</td>
                                        </tr>`;
                                    tbody.innerHTML += row;
                                });

                                lokasiEl.textContent = data.catatan_lokasi_pengantaran || '-';
                                penolakanEl.textContent = data.catatan_penolakan || '-';

                                if (data.bukti_pengantaran) {
                                    buktiImg.src = data.bukti_pengantaran;
                                    buktiImg.style.display = 'block';
                                    buktiText.style.display = 'none';
                                } else {
                                    buktiImg.style.display = 'none';
                                    buktiText.style.display = 'block';
                                }
                            })
                            .catch(error => {
                                alert("Gagal memuat data pesanan. Lihat console untuk detail.");
                                console.error(error);
                            });
                    }

                    document.addEventListener('DOMContentLoaded', () => {
                        const filterDate = document.getElementById('filter_date');
                        const startDate = document.getElementById('start_date');
                        const endDate = document.getElementById('end_date');

                        function toggleFilterDate() {
                            filterDate.disabled = startDate.value || endDate.value;
                        }

                        startDate.addEventListener('input', toggleFilterDate);
                        endDate.addEventListener('input', toggleFilterDate);
                        toggleFilterDate();
                    });
                </script>
            </div>
        </div>
    </div>
</x-master-layout>
