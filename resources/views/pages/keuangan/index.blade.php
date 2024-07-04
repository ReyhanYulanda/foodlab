<x-master-layout>
    <div class="main-content">
        <section class="bg-white rounded p-10">
            <div class="flex flex-col gap-y-4">
                <div class="flex flex-col space-y-6 md:space-y-0 md:flex-row justify-between">
                </div>
        <section class="grid md:grid-cols-2 xl:grid-cols-4 gap-6">
            <div class="flex items-center p-8 bg-white shadow rounded-lg">
                <div>
                    <span class="block text-gray-500">Pending</span>
                    <span class="block text-2xl font-bold">Rp{{number_format($summary->jumlah_pending, 0, ',', '.')}}</span>
                </div>
            </div>
            <div class="flex items-center p-8 bg-white shadow rounded-lg">
                <div>
                    <span class="block text-gray-500">Minggu ini </span>
                    <span class="block text-2xl font-bold">Rp{{number_format($summary->jumlah_minggu_ini, 0, ',', '.')}}</span>
                </div>
            </div>
            <div class="flex items-center p-8 bg-white shadow rounded-lg">
                <div>
                    <span class="block text-gray-500">Bulan ini</span>
                    <span class="block text-2xl font-bold">Rp{{number_format($summary->jumlah_bulan_ini, 0, ',', '.')}}</span>
                </div>
            </div>
            <div class="flex items-center p-8 bg-white shadow rounded-lg">
                <div>
                    <span class="block text-gray-500">Total Keseluruhan</span>
                    <span class="block text-2xl font-bold">Rp{{number_format($summary->jumlah_semua, 0, ',', '.')}}</span>
                </div>
            </div>
        </section>
    </div>

        <div class="text-black bg-white rounded py-10">
            <div class="px-8">

                <div class="d-flex flex-row justify-content-between">
                    <h2 class="text-2xl font-semibold leading-tight">Rincian Penjualan</h2>
                    <div class="d-flex align-items-center">
                        <p class="mr-4">Halaman : </p>
                    <select name="page" id="select_page">
                        @for ($i = 1 ; $i <= $lastPage ; $i++)
                            <option value="{{ $i }}" {{ $i == $currentPage ? "selected" : "" }}/>{{ $i }}</option>
                        @endfor
                    </select>
                    </div>
                    <script>

                        document.getElementById('select_page').addEventListener('change', (event) => {
                            window.location.replace(`?page=${event.target.value}`);
                        })
                    </script>
                </div>
                <div class="-mx-4 sm:-mx-8 px-4 sm:px-8 py-4 overflow-x-auto">
                    <div class="inline-block min-w-full rounded-lg overflow-hidden">
                       @include('components.tabel_pemesanan', ['dataPesanan' => $dataPesanan, 'startIndex' => ($currentPage - 1) * 15 + 1])
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('js')
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                let tabTogglers = document.querySelectorAll("#tabs a");

                // Hide content of Pesanan Dibatalkan tab initially
                let tabContents = document.querySelectorAll("#tab-content-second > div");
                tabContents.forEach(function(tabContent) {
                    tabContent.classList.add("hidden");
                });

                tabTogglers.forEach(function(toggler) {
                    toggler.addEventListener("click", function(e) {
                        e.preventDefault();

                        let tabName = this.getAttribute("href");

                        let tabContents = document.querySelectorAll(
                            "#tab-contents-first > div, #tab-content-second > div");

                        tabContents.forEach(function(tabContent) {
                            if (tabContent.id === tabName.slice(1)) {
                                tabContent.classList.remove("hidden");
                            } else {
                                tabContent.classList.add("hidden");
                            }
                        });

                        tabTogglers.forEach(function(tabToggler) {
                            tabToggler.parentElement.classList.remove("border-t", "border-r",
                                "border-l", "-mb-px", "bg-white");
                        });

                        this.parentElement.classList.add("border-t", "border-r", "border-l", "-mb-px",
                            "bg-white");
                    });
                });
            });
        </script>



    @endpush
</x-master-layout>
