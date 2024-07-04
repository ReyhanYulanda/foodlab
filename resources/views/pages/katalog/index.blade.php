@push('css')
    <style>
        .dropdown:hover .dropdown-menu {
            display: block;
            z-index: 5;
        }
        /* .editable-field{
            display: flex;
            justify-content: space-between;
        }

        .editable-input{
            border: none;
            font-size: inherit;
            background: inherit;
            color: inherit;
            transition: .3s all linear
            &:focus
            outline: none
            background: white
            color: #545454
        }


        .edit-button{
            cursor: pointer;
            font-size: 5em;
            position: relative;
            top: 5px;
        }

        .hide{
            visibility: hidden !important;
        }

        .editing{
            background: #E9F5FA;
            color: #f1f1f1;
            box-shadow: -2px 0px 0px #0081C6;
        } */
    </style>
@endpush
<x-master-layout>
    <div class="main-content">
        <div class="bg-white p-3 rounded ">
            <div class="container px-8">
                <div class="mt-0 mb-0">
                    <div>
                        <h2 class="text-2xl font-semibold leading-tight">{{ $tenant->listMenu->count() }} Produk</h2>
                    </div>
                    <div class="-mx-4 sm:-mx-8 px-4 sm:px-8 py-4 overflow-x-auto">
                        <div class="inline-block min-w-full rounded-lg overflow-hidden">
                            <table class="min-w-full leading-normal">
                                <thead>
                                    <tr><th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                        No
                                        </th>
                                        <th
                                            class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                            Menu
                                        </th>
                                        <th
                                            class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th
                                            class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                            Harga
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tenant->listMenu ?? [] as $menu)
                                        <tr>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <p class="text-gray-900 whitespace-no-wrap">
                                                    {{ $loop->iteration }}
                                                </p>
                                            </td>
                                            <td class="px-5 border-b border-gray-200 bg-white text-sm">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 w-10 h-10">
                                                        <img class="w-full h-full rounded-full"
                                                            src="{{url('')."{$menu->gambar}"}}"
                                                            alt="" />
                                                    </div>
                                                    <div class="ml-3">
                                                        <p class="text-gray-900 whitespace-no-wrap">
                                                            {{$menu->nama ?? $menu->nama}}
                                                        </p>
                                                        {{-- <p class="text-gray-600 whitespace-no-wrap">000004</p> --}}
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="px-5 border-b border-gray-200 bg-white text-sm">
                                                <div class="py-10">

                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input update_stock" type="checkbox" role="switch" id="flexSwitchCheckChecked" {{$menu->isReady ? 'checked' : ""}} data-id-menu="{{$menu->id}}">
                                                        <label class="form-check-label" for="flexSwitchCheckChecked">{{$menu->isReady ? 'Tersedia' : "Kosong"}}</label>
                                                      </div>
                                                </div>
                                            </td>
                                            <td class="px-5 border-b border-gray-200 bg-white text-sm">
                                                {{-- <div class="editable-field">
                                                    <input class="editable-input" type="text" value="Other" placeholder="Click The Edit Icon" readonly/>
                                                    <i class="fas fa-edit edit-button"></i>
                                                  </div> --}}
                                                <p class="text-gray-900 whitespace-no-wrap">Rp{{number_format($menu->harga, 0, ',', '.')}}
                                                    <i class="fas fa-edit" data-bs-toggle="modal" data-bs-target="#exampleModal" data-bs-harga="{{$menu->harga}}" data-bs-id="{{$menu->id}}"></i></p>
                                                {{-- <p class="text-gray-600 whitespace-no-wrap">Due in 3 days</p> --}}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h1 class="modal-title fs-5" id="exampleModalLabel">Update Harga</h1>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <form>
                <div class="mb-3">
                  <label for="recipient-name" class="col-form-label">Harga:</label>
                  <input type="number" class="form-control" id="recipient-name">
                </div>
              </form>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Batal</button>
              <button type="button" class="btn btn-success">Simpan Perubahan</button>
            </div>
          </div>
        </div>
      </div>

    @push('js')
        <script>
        document.addEventListener("DOMContentLoaded", function() {
        const searchInput = document.getElementById('searchInput');
        const searchButton = document.getElementById('searchButton');

        // Event listener untuk tombol pencarian
        searchButton.addEventListener('click', function() {
            const searchTerm = searchInput.value.trim();
            if (searchTerm !== '') {
                // Lakukan sesuatu, misalnya arahkan pengguna ke halaman pencarian dengan query yang sesuai
                window.location.href = '?search=' + encodeURIComponent(searchTerm);
            }else{
                window.location.href = '/katalog';
            }
        });

        // Event listener jika pengguna menekan tombol enter pada keyboard saat berfokus pada input pencarian
        searchInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                const searchTerm = searchInput.value.trim();
                if (searchTerm !== '') {
                    // Lakukan sesuatu, misalnya arahkan pengguna ke halaman pencarian dengan query yang sesuai
                    window.location.href = '?search=' + encodeURIComponent(searchTerm);
                }else{
                window.location.href = 'katalog';
                }
            }
        });
    });
        </script>
        <script>
            $(document).ready(function() {
        $('.update_stock').on('click', function() {
            let idMenu = $(this).data('id-menu');
            let token = "{{ session()->get('_token') }}";
            console.log(token);
            if ($(this).is(':checked')) {
                // Send AJAX request when checkbox is checked
                $.ajax({
                    url: '/menu/' + idMenu, // Replace with your actual API endpoint URL
                    type: 'POST', // Adjust method based on API requirements (POST, GET, etc.)
                    data: { // Send any necessary data to the API
                        isReady: 1,
                        _token: token // Include CSRF token in the data
                    },
                    success: function(response) {
                        console.log("Data updated successfully:", response);
                        // Reload the page after successful update
                        location.reload();
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error("Error updating data:", textStatus, errorThrown);
                        // Handle errors appropriately, e.g., display error message to user
                    }
                });
            } else {
                $.ajax({
                    url: '/menu/' + idMenu, // Replace with your actual API endpoint URL
                    type: 'POST', // Adjust method based on API requirements (POST, GET, etc.)
                    data: { // Send any necessary data to the API
                        isReady: 0,
                        _token: token // Include CSRF token in the data
                    },
                    success: function(response) {
                        console.log("Data updated successfully:", response);
                        // Reload the page after successful update
                        location.reload();
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error("Error updating data:", textStatus, errorThrown);
                        // Handle errors appropriately, e.g., display error message to user
                    }
                });
            }
        });
    });
        </script>
        <script>
            $("table").dataTable({
            pageLength: 5,
            lengthMenu: [[5, 10, 20, -1], [5, 10, 20, "Semua"]],
            bInfo: false,
        });
            const exampleModal = document.getElementById('exampleModal')
                if (exampleModal) {
                exampleModal.addEventListener('show.bs.modal', event => {
                    // Button that triggered the modal
                    const button = event.relatedTarget
                    // Extract info from data-bs-* attributes
                    const harga = button.getAttribute('data-bs-harga')
                    // If necessary, you could initiate an Ajax request here
                    // and then do the updating in a callback.

                    const idMenu = button.getAttribute('data-bs-id')
                    console.log(idMenu)
                    let token = "{{ session()->get('_token') }}";

                    const submitButton = exampleModal.querySelector('.btn-success');

                    // Set the input value to the harga
                    const modalBodyInput = exampleModal.querySelector('.modal-body input')
                    modalBodyInput.value = harga;

                    // Add click event listener to the submit button
                    submitButton.addEventListener('click', function() {
                        console.log('jhal')
                        // Get the updated harga from the input field
                        const updatedHarga = modalBodyInput.value;

                        // AJAX request to update the data
                        $.ajax({
                            url: '/menu/'+idMenu, // Replace with your actual API endpoint URL
                            type: 'POST', // Adjust method based on API requirements (POST, GET, etc.)
                            data: {
                                harga: updatedHarga,
                                _token: token
                            },
                            success: function(response) {
                                console.log("Data updated successfully:", response);
                                // Handle successful response, e.g., update UI elements
                                // Close the modal after successful update if needed
                                location.reload();
                            },
                            error: function(jqXHR, textStatus, errorThrown) {
                                console.error("Error updating data:", textStatus, errorThrown);
                                // Handle errors appropriately, e.g., display error message to user
                            }
                        });
                    });

                    // Update the modal's content.
                    const modalTitle = exampleModal.querySelector('.modal-title')


                    console.log(button);

                    modalBodyInput.value = harga
                })
                }
        </script>
    @endpush
</x-master-layout>
