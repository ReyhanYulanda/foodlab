<?php

namespace App\Http\Controllers\Api\Voucher;

use App\Http\Controllers\Controller;
use App\Models\Cashback;
use App\Models\Voucher;
use App\Response\ResponseApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VoucherController extends Controller
{
    public function postGetVoucher(Request $request, $referral_code)
    {
        $user = Auth::user();

        // Cari cashback yang valid
        $cashback = Cashback::where('referral_code', $referral_code)
            ->where('is_valid', true)
            ->first();

        if (!$cashback) {
            return response()->json([
                'status' => 'error',
                'message' => 'Promo tidak ditemukan atau tidak valid'
            ], 404);
        }

        // Cek apakah user sudah pernah ambil voucher ini
        $alreadyClaimed = Voucher::where('user_id', $user->id)   
            ->where('cashback_id', $cashback->id)
            ->exists();

        if ($alreadyClaimed) {
            return response()->json([
                'status' => 'error',
                'message' => 'Promo sudah diambil sebelumnya'
            ], 400);
        }

        // Pastikan masih ada quantity
        if ($cashback->quantity <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Promo sudah habis'
            ], 400);
        }

        // Buat voucher baru
        $voucher = Voucher::create([
            'user_id'     => $user->id,
            'cashback_id' => $cashback->id,
            'quantity'    => $cashback->max_used,
        ]);

        // Decrement quantity cashback
        // $cashback->decrement('quantity');
        $voucher->load('cashback');

        return response()->json([
            'status'  => 'success',
            'message' => 'Voucher berhasil diklaim',
            'data'    => $voucher
        ]);
    }

    public function getListVoucher()
    {
        $user = Auth::user();

        $vouchers = Voucher::with('cashback')
            ->where('user_id', $user->id)
            ->whereHas('cashback', function ($query) {
                $query->where('is_valid', 1) 
                    ->whereDate('start_date', '<=', now())
                    ->whereDate('end_date', '>=', now())
                    ->where('quantity', '>', 0);
            })
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $vouchers
        ]);
    }
}
