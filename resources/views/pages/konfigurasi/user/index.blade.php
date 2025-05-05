<x-master-layout>
    <div class="main-content">
        <div class="title">
            Konfigurasi
        </div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>User</h4>
                    <div class="row">
                        <div class="col-12">
                            @can('create tenant')
                                <a class="btn btn-primary add" href="{{ route('user.create') }}">Tambah</a>
                            @endcan
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <th>No</th>
                            <th>Nama User</th>
                            <th>Email</th>
                            <th>No Telefon</th>
                            <th>Profil User</th>
                            <th>Role</th>
                            <th>Action</th>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $user->name }}</td>
                                    <td>{{ $user->email ?? '-' }}</td>
                                    <td>{{ $user->phone ?? '-' }}</td>
                                    <td>
                                        <img src="{{ $user->image }}" width="100" />
                                    </td>
                                    <td class="">
                                        @foreach ($user->roles as $role)
                                            <span class="text-white bg-primary d-inline-block px-2 rounded-pill">{{$role->name}}</span>
                                        @endforeach
                                    </td>
                                    <td>
                                        <a href="{{ route('user.edit', $user->id) }}"
                                            class="btn btn-secondary">Edit</a>
                                        <form action="{{ route('user.destroy', $user->id) }}" class="d-inline"
                                            method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    @push('js')
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
