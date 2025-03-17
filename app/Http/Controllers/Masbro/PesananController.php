<?php

namespace App\Http\Controllers\Masbro;

use App\Http\Controllers\Controller;
use App\Models\Tenants;
use App\Models\Transaksi;
use App\Services\Firebases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class PesananController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
    
        if(!$user->can('read pengantaran')){
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }
    
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:diantar,selesai,siap_diantar',
            'gedung' => 'nullable',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                "status" => "Bad Request",
                "message" => $validator->errors()->all()
            ], 400);
        }
    
        try {
            $transaksi = Transaksi::with(['listTransaksiDetail.menus.tenants', 'user']);
    
            if($request->has('status')){
                if (in_array($request->status, ['diantar', 'selesai'])) {
                    $transaksi = $transaksi->where('driver_id', $user->id);
                }
                $transaksi = $transaksi->where('status', $request->status);
            }
    
            if($request->has('gedung')){
                $transaksi = $transaksi->where('gedung', $request->gedung);
            }
    
            $transaksi = $transaksi->get();
    
            return response()->json([
                "status" => "success",
                "message" => "Berhasil mengambil data",
                "data" => [
                    'transaksi' => $transaksi,
                ]
            ]);
        } catch (Throwable $th) {
            Log::error($th->getMessage());
            return response()->json([
                "status" => "server error",
                "message" => "terjadi kesalahan di server"
            ], 500);
        }
    }

    public function update(Request $request, $transaksiId, Firebases $firebases)
    {
        $user = $request->user();
    
        if(!$user->can('update pengantaran')){
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }
    
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:diantar,selesai,siap_diantar',
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
                $transaksi->driver_id = $user->id; // Tambahkan ID driver
                $transaksi->save();
                $status = str_replace('_', ' ', $transaksi->status);
                
                if($transaksi->metode_pembayaran != 'transfer'){
                    $transaksi->listTransaksiDetail()->update(['status' => $transaksi->status]);
                }
                if ($transaksi->status == 'diantar') {
                    $firebases->withNotification('Pesanan Sedang Diantar', "Pesanan {$transaksi->id} sedang diantar")
                        ->sendMessages($transaksi->user->fcm_token);
                }
    
                if ($transaksi->status == 'selesai') {
                    $firebases->withNotification('Pesanan Sudah Sampai', "Pesanan {$transaksi->id} sudah sampai. Selamat Menikmati 😋")
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