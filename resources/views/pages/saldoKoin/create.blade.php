<x-master-layout>
    <div class="main-content">
        <div class="title">
            Tambah Saldo Koin
        </div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>Form Tambah Saldo</h4>
                    <a class="btn btn-secondary" href="{{ route('saldoKoin.index') }}">Kembali</a>
                </div>
                <div class="card-body">
                    <form action="{{ route('saldoKoin.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="user_id" class="form-label">Pilih User</label>
                            <select name="user_id" class="form-control" required>
                                <option value="">-- Pilih User --</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">
                                        {{ $user->name }} - {{ $user->email }} (ID: {{ $user->id }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="jumlah" class="form-label">Jumlah Saldo</label>
                            <input type="number" name="jumlah" class="form-control" required min="0">
                        </div>

                        <button type="submit" class="btn btn-success">Simpan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-master-layout>
