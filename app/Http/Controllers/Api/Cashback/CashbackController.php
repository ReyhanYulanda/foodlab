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
        $cashbacks = Cashback::where('is_valid', true)->get();

        return response()->json([
            'status' => 'success',
            'data' => $cashbacks
        ]);
    }
}
