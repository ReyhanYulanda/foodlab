<x-master-layout>
    <div class="main-content">
        <div class="title">
            Payout Driver
        </div>
        <div class="content-wrapper">
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header">
                    <h4>Form Tambah Driver</h4>
                </div>
                <div class="card-body">
                    <form id="form-tambah-driver">
                        <div class="flex flex-wrap gap-4 items-end">
                            <div class="form-group mb-0 flex-1 min-w-[200px]">
                                <label for="nama_driver">Nama Driver</label>
                                <input type="text" id="nama_driver" class="form-control" required>
                            </div>
                            <div class="form-group mb-0 flex-1 min-w-[200px]">
                                <label for="no_rekening">Nomor Rekening</label>
                                <input type="number" id="no_rekening" class="form-control" required>
                            </div>
                            <div class="form-group mb-0 flex-1 min-w-[200px]">
                                <label for="jumlah_payout">Jumlah Payout</label>
                                <input type="number" id="jumlah_payout" class="form-control" required>
                            </div>
                            <div class="form-group mb-0">
                                <button type="submit" class="btn btn-primary" style="margin-top: 1.8rem;">Tambah
                                    Driver</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header flex justify-between items-center w-full">
                    <h4>Daftar Driver Payout</h4>
                    <form action="{{ route('transaksi.driver.payout.export') }}" method="POST">
                        @csrf
                        <input type="hidden" name="payout_data" id="payout_data" value="[]">
                        <button type="submit" class="btn btn-success" id="btn-export-csv" disabled>Export CSV</button>
                    </form>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table w-full table-striped">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Driver</th>
                                    <th>Nomor Rekening</th>
                                    <th>Jumlah Payout</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="table-driver-body">
                                <tr>
                                    <td colspan="5" class="text-center">Belum ada driver yang ditambahkan.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const formTambah = document.getElementById('form-tambah-driver');
                const tbody = document.getElementById('table-driver-body');
                const inputPayoutData = document.getElementById('payout_data');
                const btnExportCsv = document.getElementById('btn-export-csv');

                let drivers = [];

                function formatRupiah(number) {
                    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
                }

                function renderTable() {
                    tbody.innerHTML = '';
                    if (drivers.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-gray-500">Belum ada driver yang ditambahkan.</td></tr>';
                        btnExportCsv.disabled = true;
                    } else {
                        btnExportCsv.disabled = false;
                        drivers.forEach((driver, index) => {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td>${index + 1}</td>
                                <td class="font-semibold">${driver.nama}</td>
                                <td>${driver.no_rekening}</td>
                                <td>${formatRupiah(driver.jumlah_payout)}</td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm btn-hapus" data-index="${index}">Hapus</button>
                                </td>
                            `;
                            tbody.appendChild(tr);
                        });

                        document.querySelectorAll('.btn-hapus').forEach(btn => {
                            btn.addEventListener('click', function () {
                                const index = this.getAttribute('data-index');
                                drivers.splice(index, 1);
                                renderTable();
                            });
                        });
                    }

                    inputPayoutData.value = JSON.stringify(drivers);
                }

                formTambah.addEventListener('submit', function (e) {
                    e.preventDefault();

                    const nama = document.getElementById('nama_driver').value;
                    const noRekening = document.getElementById('no_rekening').value;
                    const jumlahPayout = document.getElementById('jumlah_payout').value;

                    drivers.push({
                        nama: nama,
                        no_rekening: noRekening,
                        jumlah_payout: parseFloat(jumlahPayout)
                    });

                    document.getElementById('nama_driver').value = '';
                    document.getElementById('no_rekening').value = '';
                    document.getElementById('jumlah_payout').value = '';

                    document.getElementById('nama_driver').focus();

                    renderTable();
                });
            });
        </script>
    </div>
</x-master-layout>