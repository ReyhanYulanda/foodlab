<x-master-layout>
    @push('cssLibrary')
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @endpush
    <div class="main-content">
        <div class="title">
            Cashback
        </div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>Edit Cashback {{ $cashback->referral_code }}</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('cashback.update', $cashback->id) }}" method="POST"
                        enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="value" class="form-label">Value (%)</label>
                                    <input type="number" step="0.01" min="0" placeholder="0"
                                        class="form-control @error('value') is-invalid @enderror" id="value"
                                        name="value" value="{{ old('value', $cashback->value) }}">
                                    @error('value')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="quantity" class="form-label">Quantity</label>
                                    <input type="number" min="0" placeholder="0"
                                        class="form-control @error('quantity') is-invalid @enderror" id="quantity"
                                        name="quantity" value="{{ old('quantity', $cashback->quantity) }}">
                                    @error('quantity')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="minimal_beli" class="form-label">Minimal Beli</label>
                                    <input type="number" min="0" placeholder="0"
                                        class="form-control @error('minimal_beli') is-invalid @enderror"
                                        id="minimal_beli" name="minimal_beli"
                                        value="{{ old('minimal_beli', $cashback->minimal_beli) }}">
                                    @error('minimal_beli')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="max_cashback" class="form-label">Max Cashback</label>
                                    <input type="number" min="0" placeholder="0"
                                        class="form-control @error('max_cashback') is-invalid @enderror"
                                        id="max_cashback" name="max_cashback"
                                        value="{{ old('max_cashback', $cashback->max_cashback) }}">
                                    @error('max_cashback')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="max_used" class="form-label">Max Used</label>
                                    <input type="number" min="0" placeholder="0"
                                        class="form-control @error('max_used') is-invalid @enderror" id="max_used"
                                        name="max_used" value="{{ old('max_used', $cashback->max_used) }}">
                                    @error('max_used')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="is_valid" class="form-label">Status</label>
                                    <select class="form-control @error('is_valid') is-invalid @enderror" id="is_valid"
                                        name="is_valid">
                                        <option value="1"
                                            {{ old('is_valid', $cashback->is_valid) == 1 ? 'selected' : '' }}>
                                            Aktif
                                        </option>
                                        <option value="0"
                                            {{ old('is_valid', $cashback->is_valid) == 0 ? 'selected' : '' }}>
                                            Nonaktif
                                        </option>
                                    </select>
                                    @error('is_valid')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control @error('start_date') is-invalid @enderror"
                                        id="start_date" name="start_date"
                                        value="{{ old('start_date', $cashback->start_date ? \Carbon\Carbon::parse($cashback->start_date)->format('Y-m-d') : '') }}">
                                    @error('start_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control @error('end_date') is-invalid @enderror"
                                        id="end_date" name="end_date"
                                        value="{{ old('end_date', $cashback->end_date ? \Carbon\Carbon::parse($cashback->end_date)->format('Y-m-d') : '') }}">
                                    @error('end_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="col-4 float-right">
                            <div class="mb-3">
                                <input type="submit" class="btn btn-primary" id="basicInput" value="Simpan">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>

    @push('jsLibrary')
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    @endpush

    @push('js')
        <script>
            $('select').select2();
        </script>
        @if ($errors->any())
            @foreach ($errors->all() as $error)
                toastr.error('{{ $error }}', 'Error');
            @endforeach
        @endif
    @endpush

</x-master-layout>
