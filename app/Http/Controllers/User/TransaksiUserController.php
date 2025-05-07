<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\TransaksiSaldoKoin;
use App\Models\SaldoKoin;
use App\Models\Transaksi;
use App\Models\Pengaturan;
use App\Services\Firebases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class TransaksiUserController extends Controller
{
    public function updateStatusTransaksi(Request $request, $transaksiId, Firebases $firebases)
    {
        $user = $request->user();
        $transaksi = Transaksi::find($transaksiId);
    
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:selesai',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                "status" => "Bad Request",
                "message" => $validator->errors()
            ], 400);
        }
    
        try {
            $transaksi = Transaksi::find($transaksiId);
            if (!$transaksi) {
                return response()->json([
                    "status" => "Not Found",
                    "message" => "Transaksi tidak ditemukan"
                ], 404);
            } else {
                $transaksi->status = $request->status;
                $transaksi->save();    
                if ($transaksi->status == 'selesai') {
                    $firebases->withNotification('Pesanan Sudah Diterima', "Pesanan {$transaksi->id} Telah Diambil. Selamat menikmati! 🍽")
                        ->sendMessages($transaksi->user->fcm_token);
                }

                return response()->json([
                    "status" => "success",
                    "message" => "Pesanan {$request->status}",
                ]);
            }
        } catch (Throwable $th) {
            Log::error($th->getMessage());
            return response()->json([
                "status" => "server error",
                "message" => "terjadi kesalahan di server"
            ], 500);
        }
    }
}
