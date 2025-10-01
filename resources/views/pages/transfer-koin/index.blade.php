<x-master-layout>
    <div class="main-content">
        <div class="title">Transfer Coin</div>
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
                                        {{ $user->email }} (Saldo: {{ $user->saldo }})
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
                                        {{ $user->email }} (Saldo: {{ $user->saldo }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group mt-2">
                            <label>Jumlah</label>
                            <input type="number" name="jumlah" class="form-control" min="1" required>
                        </div>

                        <button type="submit" class="btn btn-primary mt-3">Transfer</button>
                    </form>
                </div>
            </div>
        </div>
        <script>
            $(document).ready(function() {
                $('.select2').select2({
                    placeholder: "Cari pengguna...",
                    allowClear: true
                });
            });
        </script>
    </div>
</x-master-layout>
