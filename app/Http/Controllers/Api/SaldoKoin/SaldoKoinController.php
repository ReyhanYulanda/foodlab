<?php

namespace App\Http\Controllers\Api\SaldoKoin;

use App\Http\Controllers\Controller;
use App\Services\SaldoKoin\Actions\CekSaldoAction;
use App\Services\SaldoKoin\Actions\RiwayatTransaksiAction;
use App\Services\SaldoKoin\Actions\TransferCoinAction;
use Illuminate\Http\Request;

class SaldoKoinController extends Controller
{
    public function cekSaldo(CekSaldoAction $action)
    {
        $this->authorize('read saldo_koin');

        $result = $action->execute(auth()->id());

        return response()->json([
            'success' => true,
            'saldo_koin' => $result['saldo_koin']
        ]);
    }

    public function riwayatTransaksi(Request $request, RiwayatTransaksiAction $action)
    {
        $this->authorize('read saldo_koin');

        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $transaksi = $action->execute(auth()->id(), $perPage, $page);

        return response()->json([
            'success' => true,
            'message' => 'data berhasil didapatkan',
            'transaksi' => $transaksi
        ]);
    }

    public function transferCoin(Request $request, TransferCoinAction $action)
    {
        $this->authorize('read transfer_coin');

        $result = $action->execute($request);

        if ($result['error'] ?? false) {
            return response()->json([
                'message' => $result['message']
            ], $result['status']);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['data']
        ]);
    }
}
?>