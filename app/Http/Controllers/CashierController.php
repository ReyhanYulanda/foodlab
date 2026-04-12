<?php

namespace App\Http\Controllers;

use App\Services\Cashier\Actions\StoreCashierAction;
use App\Services\Cashier\Actions\GetCashierHistoryAction;
use App\Services\Cashier\Actions\GetCashierHistoryByIdAction;
use App\Services\Cashier\Actions\UpdateCashierAction;
use App\Services\Cashier\Actions\DestroyCashierAction;
use Illuminate\Http\Request;

class CashierController extends Controller
{
    public function store(Request $request, StoreCashierAction $action)
    {
        $result = $action->execute($request);

        return response()->json([
            'status' => ($result['error'] ?? false) ? 'failed' : 'success',
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['status'] ?? 200);
    }

    public function getHistory(Request $request, GetCashierHistoryAction $action)
    {
        $result = $action->execute($request);

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => $result['data'],
        ], 200);
    }

    public function getHistoryById(Request $request, $id, GetCashierHistoryByIdAction $action)
    {
        $result = $action->execute($id, $request);

        if ($result['error'] ?? false) {
            return response()->json([
                'status' => 'failed',
                'message' => $result['message'],
            ], $result['status']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => $result['data'],
        ], 200);
    }

    public function update(Request $request, $id, UpdateCashierAction $action)
    {
        $result = $action->execute($id, $request);

        if ($result['error'] ?? false) {
            return response()->json([
                'status' => 'failed',
                'message' => $result['message'],
            ], $result['status']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => $result['data'],
        ], 200);
    }

    public function destroy(Request $request, $id, DestroyCashierAction $action)
    {
        $result = $action->execute($id, $request);

        if ($result['error'] ?? false) {
            return response()->json([
                'status' => 'failed',
                'message' => $result['message'],
            ], $result['status']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
        ], 200);
    }
}
?>