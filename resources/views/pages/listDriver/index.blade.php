@extends('layouts.app') {{-- Sesuaikan layout utama kamu --}}

@section('content')
    <div class="container">
        <h4 class="mb-3">List Driver Aktif ({{ $jumlahDriver }})</h4>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Driver</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($drivers as $index => $driver)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $driver->name }}</td>
                        <td>
                            <form action="{{ route('list-driver.setOffline', $driver->id) }}" method="POST"
                                onsubmit="return confirm('Yakin ingin mematikan status online driver ini?')">
                                @csrf
                                <button type="submit" class="btn btn-danger btn-sm">Mati</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center">Tidak ada driver aktif</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
