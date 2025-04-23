<x-master-layout>
    <div class="main-content">
        <div class="title">
            Konfigurasi
        </div>
        <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h4>tenant</h4>
                    <div class="row">
                        <div class="col-12">
                            @can('create tenant')
                                <a class="btn btn-primary add" href="{{ route('tenant.create') }}">Tambah</a>
                            @endcan
                        </div>
                    </div>
                </div>
                <div class="card-body table-responsive"">
                    <table class="table table-striped">
                        <thead>
                            <th>No</th>
                            <th>Nama Tenant</th>
                            <th>Kavling</th>
                            <th>Jam Buka</th>
                            <th>Jam Tutup</th>
                            <th>Pemilik</th>
                            <th>No. Telepon</th> 
                            <th>No. Rekening Toko</th> 
                            <th>No. Rekening Pribadi</th> 
                            <th>Gambar</th>
                            <th>Status Toko</th>
                            <th>Action</th>
                        </thead>
                        <tbody>
                            @foreach ($tenants as $tenant)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $tenant->nama_tenant }}</td>
                                    <td>{{ $tenant->nama_kavling ?? '-' }}</td>
                                    <td>{{ $tenant->jam_buka }}</td>
                                    <td>{{ $tenant->jam_tutup }}</td>
                                    <td>{{ @$tenant->pemilik->name }}</td>
                                    <td>{{ @$tenant->pemilik->phone ?? '-' }}</td> 
                                    <td>{{ $tenant->no_rekening_toko ?? '-' }}</td> 
                                    <td>{{ $tenant->no_rekening_pribadi ?? '-' }}</td> 
                                    <td>
                                        <a href="#" data-bs-toggle="modal" data-bs-target="#imageModal{{ $loop->index }}">
                                            <img src="{{ $tenant->gambar }}" alt="Gambar Tenant" class="img-fluid" width="200px">
                                        </a>
                                    </td>
                                    <td>
                                        @if ($tenant->is_open)
                                            <span class="badge bg-success">Buka</span>
                                        @else
                                            <span class="badge bg-danger">Tutup</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('tenant.edit', $tenant->id) }}"
                                            class="btn btn-secondary">Edit</a>
                                        <form action="{{ route('tenant.destroy', $tenant->id) }}" class="d-inline"
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

    <!-- Modal untuk menampilkan gambar -->
    @foreach ($tenants as $tenant)
        <div class="modal fade" id="imageModal{{ $loop->index }}" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Gambar Tenant</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img src="{{ $tenant->gambar }}" alt="Gambar Tenant" class="img-fluid">
                    </div>
                </div>
            </div>
        </div>
    @endforeach

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