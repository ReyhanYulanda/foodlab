<?php

namespace App\Services\AutoCancel\Services;

use App\Models\Transaksi;
use App\Services\AutoCancel\Helpers\FeeHelper;
use Illuminate\Support\Facades\Log;

class OngkirSwapExecutor
{
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
        $tempOngkir = $cancelTx->ongkos_kirim;
        $cancelTx->ongkos_kirim = $activeTx->ongkos_kirim;
        $activeTx->ongkos_kirim = $tempOngkir;

        Log::info("SWAP: transaksi #{$cancelTx->id} ({$tempOngkir}) <-> #{$activeTx->id} ({$activeTx->ongkos_kirim})");

        if ($totalItems > 10) {
            $activeTx->ongkos_kirim = max($activeTx->ongkos_kirim - $x, 0);
            Log::info("Kurangi X={$x} untuk transaksi aktif #{$activeTx->id}");
        }

        $refund = FeeHelper::extraFeeRefundSalahSatu($cancelItems, $activeItems, $totalItems);
        $cancelTx->ongkos_kirim = $cancelOngkirMulti + $refund;
        $cancelTx->total = $cancelTx->sub_total + $cancelOngkirMulti + $refund;

        $this->recalculateActiveTotal($activeTx, $activeBaseOngkir, $priorityOngkir, $multitenantOngkir, $totalItems, $x, $isPriority);

        $cancelTx->save();
        $activeTx->save();

        Log::info("Swap completed for multitenant");
        Log::info("   Cancel #{$cancelTx->id} ongkir: {$cancelTx->ongkos_kirim}");
        Log::info("   Active #{$activeTx->id} ongkir: {$activeTx->ongkos_kirim}");
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
        if ($totalItems <= 10) {
            $this->addMultitenantOngkirIfPriority($activeTx, $multitenantOngkir, $isPriority);
            return;
        }

        $this->reduceLargerOngkir($cancelTx, $activeTx, $x);

        $refund = FeeHelper::extraFeeRefundSalahSatu($cancelItems, $activeItems, $totalItems);
        $cancelTx->ongkos_kirim = $cancelOngkirMulti + $refund;
        $cancelTx->total = $cancelTx->sub_total + $cancelOngkirMulti + $refund;

        $this->recalculateActiveTotal($activeTx, $activeBaseOngkir, $priorityOngkir, $multitenantOngkir, $totalItems, $x, $isPriority);

        $cancelTx->save();
        $activeTx->save();
    }

    private function addMultitenantOngkirIfPriority(Transaksi $activeTx, int $multitenantOngkir, bool $isPriority): void
    {
        if ($isPriority) {
            $activeTx->total += $multitenantOngkir;
            $activeTx->ongkos_kirim += $multitenantOngkir;
            $activeTx->save();
        }
    }

    private function reduceLargerOngkir(Transaksi $cancelTx, Transaksi $activeTx, int $x): void
    {
        if ($cancelTx->ongkos_kirim > $activeTx->ongkos_kirim) {
            $cancelTx->ongkos_kirim = max($cancelTx->ongkos_kirim - $x, 0);
            Log::info("Kurangi X={$x} dari cancel #{$cancelTx->id}");
        } else {
            $activeTx->ongkos_kirim = max($activeTx->ongkos_kirim - $x, 0);
            Log::info("Kurangi X={$x} dari active #{$activeTx->id}");
        }
    }

    private function recalculateActiveTotal(
        Transaksi $activeTx,
        int $activeBaseOngkir,
        int $priorityOngkir,
        int $multitenantOngkir,
        int $totalItems,
        int $x,
        bool $isPriority
    ): void {
        if ($isPriority) {
            $activeTx->total = ($activeTx->sub_total + $activeBaseOngkir + $priorityOngkir + FeeHelper::extraFee($totalItems)) - $x;
            if ($totalItems <= 10) {
                $activeTx->total += $multitenantOngkir;
                $activeTx->ongkos_kirim += $multitenantOngkir;
            }
        } else {
            $activeTx->total = ($activeTx->sub_total + $activeBaseOngkir + FeeHelper::extraFee($totalItems)) - $x;
        }
    }
}
