<x-master-layout>
    <div class="main-content">
        <div class="title">Transfer Koin</div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>Form Transfer Koin</h4>
                </div>
                <div class="card-body">

                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <form method="POST" action="{{ route('transfer.coin.post') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="sender_id" class="form-label">Pengirim</label>
                            <select name="sender_id" id="sender_id" class="form-control" required>
                                <option value="">-- Pilih Pengirim --</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})
                                    </option>
                                @endforeach
                            </select>
                            @error('sender_id')
                                <span class="text-danger small">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="receiver_id" class="form-label">Penerima</label>
                            <select name="receiver_id" id="receiver_id" class="form-control" required>
                                <option value="">-- Pilih Penerima --</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})
                                    </option>
                                @endforeach
                            </select>
                            @error('receiver_id')
                                <span class="text-danger small">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="jumlah" class="form-label">Jumlah Koin</label>
                            <input type="number" name="jumlah" id="jumlah" class="form-control" min="1"
                                required>
                            @error('jumlah')
                                <span class="text-danger small">{{ $message }}</span>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">Transfer</button>
                    </form>

                </div>
            </div>
        </div>
    </div>
</x-master-layout>
