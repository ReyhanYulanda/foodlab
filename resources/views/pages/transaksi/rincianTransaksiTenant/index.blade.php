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
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $detail->created_at->format('d-m-Y') }}</td>
                                    <td>{{ $detail->created_at->format('H:i:s') }}</td> 
                                    <td>{{ $detail->order_id }}</td>
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
                    {{ $transaksiDetails->links() }}
                </div>
            </div>
        </div>
    </div>
        
    <!-- Modal -->
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
                            <!-- Data diisi oleh JavaScript -->
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
