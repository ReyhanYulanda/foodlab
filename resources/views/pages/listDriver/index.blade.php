<x-master-layout>
    <div class="main-content">
        <div class="title">List Driver Aktif</div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>Total Driver Aktif: {{ $drivers->count() }}</h4>
                </div>
                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Driver</th>
                                <th>Email</th>
                                <th>Trigger</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($drivers as $index => $driver)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $driver->name }}</td>
                                    <td>{{ $driver->email }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('list-driver.setOffline', $driver->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-danger btn-sm">Mati</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center">Tidak ada driver aktif</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                </div>
            </div>
        </div>
    </div>
</x-master-layout>
