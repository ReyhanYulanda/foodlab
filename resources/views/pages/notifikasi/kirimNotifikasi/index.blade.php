<x-master-layout>
    <div class="main-content">
        <div class="title">
            Kirim Notifikasi
        </div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>Form Notifikasi</h4>
                </div>
                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <form method="POST" action="{{ route('notifikasi.kirim') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="judul">Judul Notif</label>
                            <input type="text" name="judul" id="judul" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="isi">Isi Notif</label>
                            <textarea name="isi" id="isi" class="form-control" required></textarea>
                        </div>

                        <div class="mb-3">
                            <div class="input-group mb-2">
                                <input type="text" name="search" value="{{ request('search') }}"
                                    class="form-control" placeholder="Cari nama user atau email user">
                                <button class="btn btn-primary" type="submit"
                                    formaction="{{ route('notifikasi.index') }}">Cari</button>
                            </div>

                            <table class="table table-responsive w-full">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="select-all"></th>
                                        <th>Nama</th>
                                        <th>Email</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($users as $user)
                                        <tr>
                                            <td><input type="checkbox" name="user_ids[]" value="{{ $user->id }}">
                                            </td>
                                            <td>{{ $user->name }}</td>
                                            <td>{{ $user->email }}</td>
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
                                            <option
                                                value="{{ request()->fullUrlWithQuery(['per_page' => $perPageOption]) }}"
                                                {{ request('per_page', 10) == $perPageOption ? 'selected' : '' }}>
                                                {{ $perPageOption }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <span class="ml-2">data per halaman</span>
                                </div>

                                <div>
                                    {{ $users->appends(request()->except('page'))->links() }}
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary float-end">Kirim Notif</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('select-all').addEventListener('click', function() {
            let checked = this.checked;
            document.querySelectorAll('input[name="user_ids[]"]').forEach(cb => cb.checked = checked);
        });
    </script>
</x-master-layout>
