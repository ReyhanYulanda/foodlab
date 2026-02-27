<?php

namespace App\Services\Transaksi\Actions;

use App\Models\Message;
use App\Models\Transaksi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class GetMessageDriverToBuyerAction
{
    public function execute($transaksiId)
    {
        try {
            $transaksi = Transaksi::findOrFail($transaksiId);

            $messages = Message::where('transaksi_id', $transaksiId)
                ->whereIn('sender_id', [$transaksi->user_id, $transaksi->driver_id])
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'message' => 'Berhasil mengambil pesan driver - pembeli',
                'data' => $messages
            ]);
        } catch (\Throwable $e) {
            Log::error("GetMessageDriverToBuyer Error: " . $e->getMessage());
            return response()->json([
                'message' => 'Gagal mengambil pesan',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
