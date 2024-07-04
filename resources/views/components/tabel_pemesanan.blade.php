<table class="min-w-full leading-normal">
    <thead>
        <tr>
            <th
                 class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                 No
            </th>
            <th
                class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                No Pesanan
            </th>
            <th
                class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                Tanggal
            </th>
            <th
                class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                Nama Pemesan
            </th>
            {{-- <th
                class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                Total Harga
            </th> --}}
            <th
                class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">

            </th>
        </tr>
    </thead>
    <tbody>
        @foreach ($dataPesanan as $pesanan)
            {{-- @foreach ($pesanan->listTransaksiDetail ?? [] as $detail) --}}
                <tr>
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                        <p class="text-gray-900 whitespace-no-wrap">
                            {{ $startIndex++ }}
                        </p>
                    </td>
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                        <div class="ml-3">
                            <p class="text-gray-900 whitespace-no-wrap">
                                {{ $pesanan->order_id }}
                            </p>
                        </div>
                    </td>

                    @php
                        $tanggal = strtotime($pesanan->created_at)
                    @endphp
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                        <p class="text-gray-900 whitespace-no-wrap">{{date("d M Y H:i", $tanggal)}}</p>
                        {{-- <p class="text-gray-600 whitespace-no-wrap">USD</p> --}}
                    </td>
                    {{-- <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 w-10 h-10">
                                <img class="w-full h-full rounded-full"
                                    src="{{$detail->menus->link_gambar}}"
                                    alt="" />
                            </div>
                            <div class="ml-3">
                                <p class="text-gray-900 whitespace-no-wrap">
                                    {{$detail->menus->nama}}
                                </p>
                                <p class="text-gray-600 whitespace-no-wrap">000004</p>
                            </div>
                        </div>
                    </td> --}}
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                        <p class="text-gray-900 whitespace-no-wrap">{{$pesanan->user->name}}</p>
                        {{-- <p class="text-gray-600 whitespace-no-wrap">Due in 3 days</p> --}}
                    </td>
                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                        <a href="#" onclick="toggleDetail({{$pesanan->id}})" class="text-blue-500 hover:text-blue-700">Detail</a>
                    </td>
                </tr>
                <tr id="detail-{{$pesanan->id}}" class="hidden" >
                   <td colspan="5">
                    <table class="table w-100">
                        <thead>
                            <tr>
                                <th>
                                    No
                                </th>
                                <th>
                                    Nama menu
                                </th>
                                <th>
                                    Jumlah
                                </th>
                                <th>
                                    Harga
                                </th>
                            </tr>
                        </thead>
                        <tbody>

                            @foreach ($pesanan->listTransaksiDetail as $transaksiDetail)
                            <tr>
                                <td>
                                    {{$loop->iteration}}
                                </td>
                                <td>
                                    {{$transaksiDetail->menus->nama}}
                                </td>
                                <td>
                                    {{$transaksiDetail->jumlah}}
                                </td>
                                <td>
                                    {{$transaksiDetail->harga}}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                   </td>
                </tr>

            {{-- @endforeach --}}
        @endforeach
    </tbody>
</table>
<script>
    function toggleDetail(id) {
        var detailRow = document.getElementById('detail-' + id);
        detailRow.classList.toggle('hidden');
    }
</script>
