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

                    <!-- ✅ Form search terpisah -->
                    <div class="input-group mb-2">
                        <input type="text" id="search-input" value="{{ request('search') }}" class="form-control"
                            placeholder="Cari nama user atau email user">
                        <button type="button" class="btn btn-primary" id="search-btn">Cari</button>
                    </div>

                    <!-- ✅ Form kirim notif -->
                    <form method="POST" action="{{ route('notifikasi.kirim') }}">
                        @csrf
                        <input type="hidden" name="selected_ids" id="selected_ids"
                            value="{{ request('selected_ids') }}">
                        <input type="hidden" id="all_user_ids" value="{{ $allUserIds }}">

                        <div class="mb-3">
                            <label for="judul">Judul Notif</label>
                            <input type="text" name="judul" id="judul" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="isi">Isi Notif</label>
                            <textarea name="isi" id="isi" class="form-control" required></textarea>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <button type="button" class="btn btn-secondary" id="save-temp-btn">Simpan
                                Sementara</button>
                            <button type="submit" class="btn btn-primary">Kirim Notif</button>
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
                                        <td><input type="checkbox" name="user_ids[]" value="{{ $user->id }}"></td>
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
                                            value="{{ request()->fullUrlWithQuery(['per_page' => $perPageOption, 'selected_ids' => request('selected_ids')]) }}"
                                            {{ request('per_page', 10) == $perPageOption ? 'selected' : '' }}>
                                            {{ $perPageOption }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="ml-2">data per halaman</span>
                            </div>

                            <div>
                                {{ $users->appends(request()->except('page') + ['selected_ids' => request('selected_ids')])->links() }}
                            </div>
                        </div>

                        {{-- <button type="submit" class="btn btn-primary float-end mt-3">Kirim Notif</button> --}}
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let initial = document.getElementById('selected_ids').value;
            let selectedIds = new Set(initial ? initial.split(',') : []);

            let allUserIdsRaw = document.getElementById('all_user_ids').value;
            let allUserIds = allUserIdsRaw ? allUserIdsRaw.split(',') : [];

            let selectAll = document.getElementById('select-all');

            function updateHiddenInput() {
                document.getElementById('selected_ids').value = Array.from(selectedIds).join(',');
                updateUrlWithSelectedIds();
                updatePaginationLinks();
            }

            function updateUrlWithSelectedIds() {
                let params = new URLSearchParams(window.location.search);
                params.set('selected_ids', Array.from(selectedIds).join(','));
                let newUrl = window.location.pathname + '?' + params.toString();
                window.history.replaceState({}, '', newUrl);
            }

            function registerCheckboxEvents() {
                document.querySelectorAll('input[name="user_ids[]"]').forEach(cb => {
                    cb.checked = selectedIds.has(cb.value);
                    cb.addEventListener('change', function() {
                        if (this.checked) {
                            selectedIds.add(this.value);
                        } else {
                            selectedIds.delete(this.value);
                        }
                        updateHiddenInput();
                    });
                });
            }

            if (selectAll) {
                selectAll.addEventListener('click', function() {
                    let checked = this.checked;
                    let pageUserIds = [];
                    document.querySelectorAll('input[name="user_ids[]"]').forEach(cb => {
                        pageUserIds.push(cb.value);
                    });
                    if (checked) {
                        pageUserIds.forEach(id => selectedIds.add(id));
                    } else {
                        pageUserIds.forEach(id => selectedIds.delete(id));
                    }
                    document.querySelectorAll('input[name="user_ids[]"]').forEach(cb => {
                        cb.checked = checked;
                    });
                    updateHiddenInput();
                });
            }

            let saveTempBtn = document.getElementById('save-temp-btn');
            if (saveTempBtn) {
                saveTempBtn.addEventListener('click', function() {
                    let url = new URL(window.location.href);
                    url.searchParams.set('search', ''); // kosong
                    url.searchParams.set('selected_ids', Array.from(selectedIds).join(','));
                    window.location.href = url.toString();
                });
            }

            let searchBtn = document.getElementById('search-btn');
            let searchInput = document.getElementById('search-input');
            if (searchBtn && searchInput) {
                searchBtn.addEventListener('click', function() {
                    let q = searchInput.value;
                    let url = new URL(window.location.href);
                    url.searchParams.set('search', q);
                    url.searchParams.set('selected_ids', Array.from(selectedIds).join(','));
                    window.location.href = url.toString();
                });
            }

            function updatePaginationLinks() {
                let selected = Array.from(selectedIds).join(',');
                document.querySelectorAll('.pagination a').forEach(a => {
                    let url = new URL(a.href);
                    url.searchParams.set('selected_ids', selected);
                    a.href = url.toString();
                });
                document.querySelectorAll('select#perPage option').forEach(option => {
                    let url = new URL(option.value);
                    url.searchParams.set('selected_ids', selected);
                    option.value = url.toString();
                });
            }

            registerCheckboxEvents();
            updatePaginationLinks(); // panggil di awal agar link di page pertama sudah ada selected_ids
        });
    </script>

</x-master-layout>
