<x-master-layout>
    @push('cssLibrary')
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @endpush
    <div class="main-content">
        <div class="title">
            Tenant
        </div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>Tambah Tenant</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('tenant.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col">
                                <div class="mb-3">
                                    <label for="basicInput" class="form-label">Nama Tenant</label>
                                    <input type="text" placeholder="Input Here" class="form-control" id="basicInput"
                                        name="nama_tenant">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="mb-3">
                                    <label for="basicInput" class="form-label">Nama Kavling</label>
                                    <input type="text" placeholder="Input Here" class="form-control" id="basicInput"
                                        name="nama_kavling">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="mb-3">
                                    <label for="basicInput" class="form-label">Jam Buka</label>
                                    <input type="time" placeholder="Input Here" class="form-control" id="basicInput"
                                        name="jam_buka">
                                </div>
                                <div class="mb-3">
                                    <label for="basicInput" class="form-label">Jam Tutup</label>
                                    <input type="time" placeholder="Input Here" class="form-control" id="basicInput"
                                        name="jam_tutup">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="mb-3">
                                    <label for="basicInput" class="form-label">Pemilik</label>
                                    <select name="pemilik" id="" class="form-control">
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="mb-3">
                                    <label for="basicInput" class="form-label">No Rekening Toko</label>
                                    <input type="number" placeholder="Input Here" class="form-control" id="basicInput"
                                        name="no_rekening_toko">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="mb-3">
                                    <label for="basicInput" class="form-label">No Rekening Pribadi</label>
                                    <input type="number" placeholder="Input Here" class="form-control" id="basicInput"
                                        name="no_rekening_pribadi">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="mb-3">
                                    <label for="basicInput" class="form-label">Gambar</label>
                                    <input type="file" class="form-control" id="basicInput" name="gambar">
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
        {{-- {!! $dataTable->scripts() !!}

        <script>
            $('.add').on('click', function(e){
                e.preventDefault();

                $.ajax({
                    url: this.href,
                    method: 'get',
                    success: function (response) {
                        const modal = $('#modal_action').html(response);
                        modal.modal('show');

                        $('#form_action').on('submit', function(e){
                            e.preventDefault();
                            console.log(this);

                            $.ajax({
                                url: this.action,
                                method: this.method,
                                data: new FormData(this),
                                contentType: false,
                                processData: false,
                                success: function(response){
                                    $('#modal_action').modal('hide');
                                    window.location.reload();
                                },
                                error: function(err){
                                    const errors = err.responseJSON?.errors;

                                    if (errors){
                                        for(let [key, message] of Object.entries(errors)){
                                            $(`[name=${key}]`).addClass('is-invalid').parent().append(`<div class="invalid-feedback"> ${message} </div>`);
                                        }
                                    }
                                }
                            })
                        })
                    },
                    error: function(){

                    }
                })
            });
        </script> --}}
    @endpush

</x-master-layout>
