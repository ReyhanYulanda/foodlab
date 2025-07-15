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
                        <div class="mb-3">
                            <label for="judul">Judul Notif</label>
                            <input type="text" name="judul" id="judul" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="isi">Isi Notif</label>
                            <textarea name="isi" id="isi" class="form-control" required></textarea>
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

                        <input type="hidden" name="selected_ids" id="selected_ids"
                            value="{{ request('selected_ids') }}">

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

                        <button type="submit" class="btn btn-primary float-end mt-3">Kirim Notif</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let initial = document.getElementById('selected_ids').value;
            let selectedIds = new Set(initial ? initial.split(',') : []);

            function updateHiddenInput() {
                document.getElementById('selected_ids').value = Array.from(selectedIds).join(',');
            }

            function registerCheckboxEvents() {
                document.querySelectorAll('input[name="user_ids[]"]').forEach(cb => {
                    if (selectedIds.has(cb.value)) {
                        cb.checked = true;
                    } else {
                        cb.checked = false;
                    }

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

            let selectAll = document.getElementById('select-all');
            if (selectAll) {
                selectAll.addEventListener('click', function() {
                    let checked = this.checked;
                    document.querySelectorAll('input[name="user_ids[]"]').forEach(cb => {
                        cb.checked = checked;
                        if (checked) {
                            selectedIds.add(cb.value);
                        } else {
                            selectedIds.delete(cb.value);
                        }
                    });
                    updateHiddenInput();
                });
            }

            registerCheckboxEvents();

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

            // ✅ intercept pagination click
            document.querySelectorAll('.pagination a').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    let url = new URL(this.href);
                    url.searchParams.set('selected_ids', Array.from(selectedIds).join(','));
                    window.location.href = url.toString();
                });
            });
        });
    </script>

</x-master-layout>
