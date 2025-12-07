<?php

namespace App\Actions;

use App\DTO\OrderStatusUpdateDto;
use App\Helpers\StatusRules;
use App\Models\Transaksi;
use App\Response\ResponseApi;

class ValidateOrderStatusAction
{
    /**
     * Mengembalikan ResponseApi error jika invalid, atau null jika valid.
     */
    public function execute(Transaksi $transaksi, OrderStatusUpdateDto $dto)
    {
        // Global rules
        if ($error = StatusRules::checkGlobalStatus($transaksi->status)) {
            return ResponseApi::error($error['message'], $error['code']);
        }

        // Transition rules
        if ($error = StatusRules::checkTransition($transaksi->status, $dto->status)) {
            return ResponseApi::error($error['message'], $error['code']);
        }

        return null;
    }
}
