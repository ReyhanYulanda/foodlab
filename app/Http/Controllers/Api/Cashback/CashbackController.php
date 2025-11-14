<?php

namespace App\Http\Controllers\Api\Cashback;

use App\Http\Controllers\Controller;
use App\Models\Cashback;
use App\Response\ResponseApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CashbackController extends Controller
{
    public function getListCashback()
    {
        $user = Auth::user();
        $cashbacks = Cashback::where('is_valid', true)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->where('quantity', '>', 0)
            ->whereDoesntHave('vouchers', function ($query) use ($user) {
                $query->claimedAndEmpty($user->id);
            })
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $cashbacks
        ]);
    }
}
