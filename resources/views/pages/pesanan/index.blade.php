<x-master-layout>
    <div class="main-content">
        <div class="bg-white p-5 rounded">
            <!-- Tabs -->
            <ul id="tabs" class="flex border-b">
                <li id="pesananMasuk" class="px-4 py-2"><a href="#first">Pesanan Masuk</a></li>
                <li id="pesananBatal" class="px-4 py-2"><a href="#second">Pesanan Dibatalkan</a></li>
            </ul>

            <!-- Tab Contents pesanan masuk -->
            <div id="tab-contents-first">
                <div id="first" class="p-4">
                    <div class="container mx-auto px-4 sm:px-8">
                        <div class="py-8">
                            <div>
                                {{-- <h2 class="text-2xl font-semibold leading-tight">{{ $pesananTotal }} Pesanan
                                    Masuk</h2> --}}
                                @php
                                    $totalPagePesananDitolak = $pesananDitolak->lastPage();
                                    $totalPagePesananNonDitolak = $pesananNonDitolak->lastPage();
                                    $currentPagePesananDitolak = $pesananDitolak->currentPage();
                                    $currentPagePesananNonDitolak = $pesananNonDitolak->currentPage();
                                @endphp

                            <div class="d-flex flex-row justify-content-between">
                                <h2   h2 class="text-2xl font-semibold leading-tight">Total Pesanan</h2>
                                <div class="d-flex align-items-center">
                                    <p class="mr-4">Halaman : </p>
                                    <select id="dropdown_pesanan_non_ditolak">
                                        @for ($i = 1 ; $i <= $totalPagePesananNonDitolak ; $i++)
                                            <option value="{{ $i }}" {{ $currentPagePesananNonDitolak == $i ? "selected" : ""}}>{{ $i }}</option>
                                        @endfor
                                    </select>

                                </div>


                            </div>
                            <div class="-mx-4 sm:-mx-8 px-4 sm:px-8 py-4 overflow-x-auto">
                                <div class="inline-block min-w-full rounded-lg overflow-hidden">
                                    @include('components.tabel_pemesanan' , [
                                        'dataPesanan' => $pesananNonDitolak,
                                        'startIndex' => ($currentPagePesananNonDitolak - 1) * 15 + 1])
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Tab Contents pesanan dibatalkan -->
            <div id="tab-content-second">
                <div id="second" class="p-4">
                    <div class="container mx-auto px-4 sm:px-8">
                        <div class="py-8">
                            <div>
                                {{-- <h2 class="text-2xl font-semibold leading-tight">
                                    {{ $pesananTotalBatal ?? 0 }} Pesanan Dibatalkan
                                </h2> --}}
                                <select id="dropdown_pesanan_ditolak">
                                    @for ($i = 1 ; $i <= $totalPagePesananDitolak ; $i++)
                                        <option value="{{ $i }}" {{ $currentPagePesananDitolak == $i ? "selected" : ""}}>{{ $i }}</option>
                                    @endfor
                                </select>
                                <script>
                                    document.getElementById('dropdown_pesanan_non_ditolak').addEventListener('change', (e) => {
                                        window.location.replace(`?pesanan_non_ditolak_page=${e.target.value}&pesanan_ditolak_page={{ $currentPagePesananDitolak }}`);
                                    });
                                    document.getElementById('dropdown_pesanan_ditolak').addEventListener('change', (e) => {
                                        window.location.replace(`?pesanan_non_ditolak_page={{ $currentPagePesananNonDitolak }}&pesanan_ditolak_page=${e.target.value}`);
                                    });
                                </script>
                            </div>
                            <div class="-mx-4 sm:-mx-8 px-4 sm:px-8 py-4 overflow-x-auto">
                                <div class="inline-block min-w-full rounded-lg overflow-hidden">
                                    @include('components.tabel_pemesanan', [
                                        'dataPesanan' => $pesananDitolak,
                                        'startIndex' => ($currentPagePesananDitolak - 1) * 15 + 1])
                                </div>
                            </div>
                        </div>
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
