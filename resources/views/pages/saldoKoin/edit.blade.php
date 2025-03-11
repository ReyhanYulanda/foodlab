<x-master-layout>
    <div class="main-content">
        <div class="title">
            Edit Saldo Koin
        </div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>Form Edit Saldo</h4>
                    <a class="btn btn-secondary" href="{{ route('saldoKoin.index') }}">Kembali</a>
                </div>
                <div class="card-body">
                    <form action="{{ route('saldoKoin.update', $saldo->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="jumlah" class="form-label">Jumlah Saldo</label>
                            <input type="number" name="jumlah" class="form-control" value="{{ $saldo->jumlah }}" required min="0">
                        </div>

                        <button type="submit" class="btn btn-primary">Update</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-master-layout>
