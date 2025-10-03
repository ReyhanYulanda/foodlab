<x-master-layout>
    <div class="main-content">
        <div class="title">Transfer Koin</div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-body">

                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('transfer.coin.store') }}">
                        @csrf
                        <div class="form-group">
                            <label>Pengirim</label>
                            <select name="sender_id" class="form-control select2">
                                <option value="">-- Pilih Pengirim --</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">
                                        {{ $user->email }} (Saldo: {{ number_format($user->saldo, 0, ',', '.') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group mt-2">
                            <label>Penerima</label>
                            <select name="receiver_id" class="form-control select2">
                                <option value="">-- Pilih Penerima --</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">
                                        {{ $user->email }} (Saldo: {{ number_format($user->saldo, 0, ',', '.') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group mt-2">
                            <label>Jumlah</label>
                            <input type="text" id="jumlah" name="jumlah" class="form-control" required>
                        </div>

                        <button type="submit" class="btn btn-primary mt-3">Transfer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('js')
        <script>
            $(document).ready(function() {
                $('.select2').select2({
                    placeholder: "Cari pengguna...",
                    allowClear: true
                });

                // format angka ribuan di input jumlah
                const jumlahInput = document.getElementById('jumlah');

                jumlahInput.addEventListener('input', function() {
                    let value = this.value.replace(/\./g, ''); // hapus titik lama
                    if (!isNaN(value) && value !== "") {
                        this.value = new Intl.NumberFormat('id-ID').format(value);
                    } else {
                        this.value = "";
                    }
                });

                // sebelum form submit, ubah ke angka murni (hapus titik)
                jumlahInput.form.addEventListener('submit', function() {
                    jumlahInput.value = jumlahInput.value.replace(/\./g, '');
                });
            });
        </script>
    @endpush
</x-master-layout>
