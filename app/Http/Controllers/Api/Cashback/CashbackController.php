<?php

namespace App\Http\Controllers\Api\Cashback;

use App\Http\Controllers\Controller;
use App\Models\Cashback;
use App\Response\ResponseApi;
use Illuminate\Http\Request;

class CashbackController extends Controller
{
    public function getListCashback()
    {
        $cashbacks = Cashback::where('is_valid', true)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->where('quantity', '>', 0)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $cashbacks
        ]);
    }
}
