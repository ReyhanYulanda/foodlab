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
                    <table class="table table-responsive w-full">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Waktu</th>
                                <th>No Pesanan</th>
                                <th>Nama Pemesan</th>
                                <th>Status Pemesan</th>
                                <th>Nama Pengantar</th>
                                <th>List Pesanan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transaksiDetails as $key => $detail)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $detail->transaksi->created_at->format('d-m-Y') }}</td>
                                    <td>{{ $detail->transaksi->created_at->format('H:i:s') }}</td> 
                                    <td>{{ 'ORDER' . date('Ymd', strtotime($detail->tanggal)) . str_pad($detail->id, 5, '0', STR_PAD_LEFT) }}</td>
                                    <td>{{ $detail->transaksi->user->name ?? '-' }}</td> 
                                    <td>
                                        {{ $detail->transaksi->isAntar == 1 ? 'Pesan Antar' : 'Ambil Sendiri' }}
                                    </td>
                                    <td>-</td>
                                    <td>
                                        <a href="#" class="btn btn-info ms-2"
                                            onclick="showPesanan('{{ $detail->menus->nama }}', '{{ $detail->jumlah }}', '{{ $detail->harga }}')"
                                            data-bs-toggle="modal" data-bs-target="#pesananModal">
                                                Liat Pesanan
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="pesananModal" tabindex="-1" aria-labelledby="pesananModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pesananModalLabel">Detail Pesanan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table">
                        <tr>
                            <th>Nama Menu</th>
                            <td id="namaMenu"></td>
                        </tr>
                        <tr>
                            <th>Jumlah</th>
                            <td id="jumlahPesanan"></td>
                        </tr>
                        <tr>
                            <th>Harga</th>
                            <td id="hargaPesanan"></td>
                        </tr>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showPesanan(nama, jumlah, harga) {
            document.getElementById('namaMenu').innerText = nama;
            document.getElementById('jumlahPesanan').innerText = jumlah;
            document.getElementById('hargaPesanan').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(harga);
        }
    </script>
</x-master-layout>
