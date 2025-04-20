<x-master-layout>
    <div class="main-content">
        <div class="title">
            Rekap Pendapatan Driver
        </div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>Pendapatan Kotor & Bersih</h4>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <table class="table table-responsive w-full table-striped">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Driver</th>
                                <th>Pendapatan Kotor</th>
                                <th>Pendapatan Bersih</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data as $index => $item)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $item->driver->name ?? 'Tidak diketahui' }}</td>
                                    <td>Rp {{ number_format($item->pendapatan_kotor, 0, ',', '.') }}</td>
                                    <td>Rp {{ number_format($item->pendapatan_bersih, 0, ',', '.') }}</td>
                                    <td>
                                        <a href="{{ route('detail.transaksi.driver', $item->driver_id) }}" class="btn btn-primary btn-sm">
                                            Rincian
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center">Tidak ada data driver.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-master-layout>
