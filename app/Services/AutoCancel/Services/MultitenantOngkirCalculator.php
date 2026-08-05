<?php

namespace App\Services\AutoCancel\Services;

use App\Models\Transaksi;

class MultitenantOngkirCalculator
{
    public function __construct(
        private OngkirConfigLoader $configLoader = new OngkirConfigLoader(),
        private OngkirSwapExecutor $swapExecutor = new OngkirSwapExecutor(),
    ) {}

    public function calculateX(int $activeItems, int $cancelItems): int
    {
        $totalItems = $activeItems + $cancelItems;

        if ($activeItems <= 10) {
            $x = ($totalItems - 10) * 500;
        } else {
            $x = ($totalItems - 10) * 500 - (($activeItems - 10) * 500);
        }

        return max($x, 0);
    }

    public function loadOngkirConfig(Transaksi $transaksi, Transaksi $cancelTx, Transaksi $activeTx): array
    {
        return $this->configLoader->load($transaksi, $cancelTx, $activeTx);
    }

    public function applySwap(
        Transaksi $cancelTx,
        Transaksi $activeTx,
        int $x,
        int $totalItems,
        int $cancelOngkirMulti,
        int $activeBaseOngkir,
        int $priorityOngkir,
        int $multitenantOngkir,
        bool $isPriority,
        int $activeItems,
        int $cancelItems
    ): void {
        $this->swapExecutor->applySwap(
            $cancelTx, $activeTx, $x, $totalItems,
            $cancelOngkirMulti, $activeBaseOngkir,
            $priorityOngkir, $multitenantOngkir,
            $isPriority, $activeItems, $cancelItems
        );
    }

    public function applyNoSwap(
        Transaksi $cancelTx,
        Transaksi $activeTx,
        int $x,
        int $totalItems,
        int $cancelOngkirMulti,
        int $activeBaseOngkir,
        int $priorityOngkir,
        int $multitenantOngkir,
        bool $isPriority,
        int $activeItems,
        int $cancelItems
    ): void {
        $this->swapExecutor->applyNoSwap(
            $cancelTx, $activeTx, $x, $totalItems,
            $cancelOngkirMulti, $activeBaseOngkir,
            $priorityOngkir, $multitenantOngkir,
            $isPriority, $activeItems, $cancelItems
        );
    }
}
