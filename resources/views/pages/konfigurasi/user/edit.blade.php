<x-master-layout>
    @push('cssLibrary')
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @endpush
    <div class="main-content">
        <div class="title">
            User
        </div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>Edit User</h4>
                </div>
                <div class="card-body">
                    <form action="{{ route('user.update', $user->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col">
                                <div class="mb-3">
                                    <label for="basicInput" class="form-label">Nama User</label>
                                    <input type="text" placeholder="Input Here" class="form-control" id="basicInput"
                                        name="nama_user" value="{{$user->name}}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="mb-3">
                                    <label for="basicInput" class="form-label">Email</label>
                                    <input type="email" placeholder="Input Here" class="form-control" id="basicInput"
                                        name="email" value="{{$user->email}}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="mb-3">
                                    <label for="basicInput" class="form-label">No Telefon</label>
                                    <input type="text" placeholder="Input Here" class="form-control" id="basicInput"
                                        name="phone" value="{{$user->phone}}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="mb-3">
                                    <label for="basicInput" class="form-label">Foto Profil User</label>
                                    <input type="file" placeholder="Input Here" class="form-control" id="basicInput"
                                        name="image" value="{{$user->image}}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="mb-3">
                                    <label for="basicInput" class="form-label">Role</label>
                                    <select name="roles[]" id="" class="form-select" multiple="multiple">
                                        <option></option>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->name }}" {{in_array($role->name, $user->roles->pluck('name')->toArray()) ? 'selected' : ''}}>{{ $role->name }}</option>
                                        @endforeach
                                    </select>
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
            $('select').select2({
                placeholder: 'Pilih Role',
                allowClear: true,
                closeOnSelect: false,
            });
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
