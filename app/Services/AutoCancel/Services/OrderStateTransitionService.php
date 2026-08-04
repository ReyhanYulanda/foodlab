<?php

namespace App\Services\AutoCancel\Services;

use App\Models\Transaksi;
use App\Models\Pengaturan;
use App\Services\AutoCancel\Helpers\FeeHelper;
use Illuminate\Support\Facades\Log;

class OrderStateTransitionService
{
    public function rejectOrder(Transaksi $transaksi, int $timeout): void
    {
        $transaksi->status = 'pesanan_ditolak';
        $transaksi->catatan_penolakan = 'Pesanan dibatalkan otomatis karena tidak direspons tenant dalam waktu ' . $timeout . ' menit.';
        $transaksi->save();
    }

    public function completeRefund(Transaksi $transaksi): void
    {
        $transaksi->status = 'refund_selesai';
        $transaksi->save();
    }

    /**
     * Process multitenant cancellation state transitions and ongkir recalculation.
     * Returns true if all transactions in the multitenant group are now done.
     */
    public function processMultitenantCancel(Transaksi $transaksi): bool
    {
        $transaksi->status = 'refund_selesai';
        $transaksi->save();

        $otherRefundCount = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
            ->where('id', '!=', $transaksi->id)
            ->where('status', 'refund_selesai')
            ->count();

        $isFirstCancel = ($otherRefundCount === 0);

        if ($isFirstCancel) {
            Log::info("Transaksi #{$transaksi->id} adalah tenant pertama yang cancel pada multitenant #{$transaksi->multitenant_id}.");
            $this->handleFirstMultitenantCancel($transaksi);
        } else {
            $this->handleSubsequentMultitenantCancel($transaksi);
            Log::info("Transaksi #{$transaksi->id} bukan tenant pertama yang cancel");
        }

        return Transaksi::where('multitenant_id', $transaksi->multitenant_id)
            ->whereNotIn('status', ['refund_selesai', 'pesanan_ditolak'])
            ->doesntExist();
    }

    private function handleFirstMultitenantCancel(Transaksi $transaksi): void
    {
        $related = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
            ->where('id', '!=', $transaksi->id)
            ->where('status', '!=', 'refund_selesai')
            ->first();

        if (!$related) {
            Log::info("Tidak ada transaksi aktif lain dalam multitenant #{$transaksi->multitenant_id}");
            return;
        }

        Log::info("Found related transaction #{$related->id} (status: {$related->status})");

        $cancelTx = $transaksi;
        $activeTx = $related;

        $activeItems = $activeTx->listTransaksiDetail->sum('jumlah');
        $cancelItems = $cancelTx->listTransaksiDetail->sum('jumlah');
        $totalItems = $activeItems + $cancelItems;

        if ($activeItems <= 10) {
            $x = ($totalItems - 10) * 500;
        } else {
            $x = ($totalItems - 10) * 500 - (($activeItems - 10) * 500);
        }
        $x = max($x, 0);

        Log::info("Items calculation: active={$activeItems}, cancel={$cancelItems}, total={$totalItems}, X={$x}");

        $needSwap = ($cancelTx->ongkos_kirim > $activeTx->ongkos_kirim);

        $cancelOngkirMulti = $cancelTx->ruangan->gedung->ongkir_multitenant ?? 0;
        $activeOngkirMulti = $activeTx->ruangan->gedung->ongkir_multitenant ?? 0;
        if ($transaksi->isPriority) {
            $activeOngkirPriority = Pengaturan::where('nama', 'ongkos_kirim_prioritas_multitenant')->value('nilai') ?? 4000;
        } else {
            $activeOngkirPriority = Pengaturan::where('nama', 'ongkos_kirim_prioritas')->value('nilai') ?? 3000;
        }
        $activeBaseOngkir = $activeTx->ruangan->gedung->ongkir ?? 0;
        $multitenantOngkir = Pengaturan::where('nama', 'ongkos_kirim_multitenant')->value('nilai') ?? 2000;

        if ($needSwap && $cancelTx->ongkos_kirim !== $activeTx->ongkos_kirim) {
            $tempOngkir = $cancelTx->ongkos_kirim;
            $cancelTx->ongkos_kirim = $activeTx->ongkos_kirim;
            $activeTx->ongkos_kirim = $tempOngkir;

            Log::info("SWAP: transaksi #{$cancelTx->id} ({$tempOngkir}) <-> #{$activeTx->id} ({$activeTx->ongkos_kirim})");

            if ($totalItems > 10) {
                $newOngkir = max($activeTx->ongkos_kirim - $x, 0);
                Log::info("Kurangi X={$x} untuk transaksi aktif #{$activeTx->id}: {$activeTx->ongkos_kirim} -> {$newOngkir}");
                $activeTx->ongkos_kirim = $newOngkir;
            }

            $cancelTx->ongkos_kirim = $cancelOngkirMulti + FeeHelper::extraFeeRefundSalahSatu($cancelItems, $activeItems, $totalItems);
            $cancelTx->total = $cancelTx->sub_total + $cancelOngkirMulti + FeeHelper::extraFeeRefundSalahSatu($cancelItems, $activeItems, $totalItems);

            if ($transaksi->isPriority) {
                $activeTx->total = ($activeTx->sub_total + $activeBaseOngkir + $activeOngkirPriority + FeeHelper::extraFee($totalItems)) - $x;
                if ($totalItems <= 10) {
                    $activeTx->total += $multitenantOngkir;
                    $activeTx->ongkos_kirim += $multitenantOngkir;
                }
            } else {
                $activeTx->total = ($activeTx->sub_total + $activeBaseOngkir + FeeHelper::extraFee($totalItems)) - $x;
            }

            $cancelTx->save();
            $activeTx->save();

            Log::info("Swap completed for multitenant #{$transaksi->multitenant_id}");
            Log::info("   Cancel #{$cancelTx->id} ongkir: {$cancelTx->ongkos_kirim}");
            Log::info("   Active #{$activeTx->id} ongkir: {$activeTx->ongkos_kirim}");
        } else {
            Log::info("Tidak perlu swap, cek kondisi:");
            Log::info("   - Cancel ongkir (#{$cancelTx->id}): {$cancelTx->ongkos_kirim}");
            Log::info("   - Active ongkir (#{$activeTx->id}): {$activeTx->ongkos_kirim}");
            Log::info("   - Need swap: " . ($needSwap ? 'YES' : 'NO'));

            if ($totalItems <= 10) {
                if ($transaksi->isPriority) {
                    $activeTx->total += $multitenantOngkir;
                    $activeTx->ongkos_kirim += $multitenantOngkir;
                    $activeTx->save();
                }
            }

            if ($totalItems > 10) {
                if ($cancelTx->ongkos_kirim > $activeTx->ongkos_kirim) {
                    $newOngkir = max($cancelTx->ongkos_kirim - $x, 0);
                    Log::info("Kurangi X={$x} dari cancel (besar) #{$cancelTx->id}: {$cancelTx->ongkos_kirim} -> {$newOngkir}");
                    $cancelTx->ongkos_kirim = $newOngkir;
                } else {
                    $newOngkir = max($activeTx->ongkos_kirim - $x, 0);
                    Log::info("Kurangi X={$x} dari active (besar) #{$activeTx->id}: {$activeTx->ongkos_kirim} -> {$newOngkir}");
                    $activeTx->ongkos_kirim = $newOngkir;
                }

                $cancelTx->ongkos_kirim = $cancelOngkirMulti + FeeHelper::extraFeeRefundSalahSatu($cancelItems, $activeItems, $totalItems);
                $cancelTx->total = $cancelTx->sub_total + $cancelOngkirMulti + FeeHelper::extraFeeRefundSalahSatu($cancelItems, $activeItems, $totalItems);

                if ($transaksi->isPriority) {
                    $activeTx->total = ($activeTx->sub_total + $activeBaseOngkir + $activeOngkirPriority + FeeHelper::extraFee($totalItems)) - $x;
                } else {
                    $activeTx->total = ($activeTx->sub_total + $activeBaseOngkir + FeeHelper::extraFee($totalItems)) - $x;
                }

                $cancelTx->save();
                $activeTx->save();
            } else {
                Log::info("Total items <= 10, no X to apply");
            }
        }
    }

    private function handleSubsequentMultitenantCancel(Transaksi $transaksi): void
    {
        $related = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
            ->where('id', '!=', $transaksi->id)
            ->first();

        $cancelTx = $transaksi;
        $activeTx = $related;

        $activeItems = $activeTx->listTransaksiDetail->sum('jumlah');
        $cancelItems = $cancelTx->listTransaksiDetail->sum('jumlah');
        $multitenantOngkir = Pengaturan::where('nama', 'ongkos_kirim_multitenant')->value('nilai') ?? 2000;

        $totalItems = $activeItems + $cancelItems;

        if ($totalItems <= 10) {
            if ($transaksi->isPriority) {
                $activeTx->total -= $multitenantOngkir;
                $activeTx->ongkos_kirim -= $multitenantOngkir;
                $activeTx->save();
            }
        }
    }
}
