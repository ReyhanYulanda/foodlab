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
            }

            // ✅ Authorization check: hanya pemilik transaksi yang boleh update
            if ($transaksi->user_id != $user->id) {
                return response()->json([
                    "status" => "Forbidden",
                    "message" => "Anda tidak diizinkan untuk mengubah status transaksi ini"
                ], 403);
            }

            // ✅ Update status
            $transaksi->status = $request->status;
            $transaksi->save();

            // ✅ Kirim notifikasi jika status selesai
            if ($transaksi->status == 'selesai') {
                $firebases
                    ->withNotification('Pesanan Sudah Diterima', "Pesanan {$transaksi->id} Telah Diambil. Selamat menikmati! 🍽")
                    ->sendMessages($transaksi->user->fcm_token);
            }

            return response()->json([
                "status" => "success",
                "message" => "Pesanan {$request->status}",
            ]);
        } catch (Throwable $th) {
            Log::error($th->getMessage());
            return response()->json([
                "status" => "Server Error",
                "message" => "Terjadi kesalahan di server"
            ], 500);
        }
    }
}
