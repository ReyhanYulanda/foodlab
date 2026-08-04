<?php

namespace App\Services\AutoCancel\Services;

use App\Models\Transaksi;
use App\Models\Pengaturan;
use Illuminate\Support\Facades\Log;

class OrderStateTransitionService
{
    public function __construct(
        private MultitenantOngkirCalculator $calculator = new MultitenantOngkirCalculator(),
    ) {}

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

        $x = $this->calculator->calculateX($activeItems, $cancelItems);

        Log::info("Items calculation: active={$activeItems}, cancel={$cancelItems}, total={$totalItems}, X={$x}");

        $config = $this->calculator->loadOngkirConfig($transaksi, $cancelTx, $activeTx);
        $needSwap = ($cancelTx->ongkos_kirim > $activeTx->ongkos_kirim);

        if ($needSwap && $cancelTx->ongkos_kirim !== $activeTx->ongkos_kirim) {
            $this->calculator->applySwap(
                $cancelTx, $activeTx, $x, $totalItems,
                $config['cancelOngkirMulti'], $config['activeBaseOngkir'],
                $config['priorityOngkir'], $config['multitenantOngkir'],
                $transaksi->isPriority, $activeItems, $cancelItems
            );
        } else {
            Log::info("Tidak perlu swap, cek kondisi:");
            Log::info("   - Cancel ongkir #{$cancelTx->id}: {$cancelTx->ongkos_kirim}");
            Log::info("   - Active ongkir #{$activeTx->id}: {$activeTx->ongkos_kirim}");
            Log::info("   - Need swap: " . ($needSwap ? 'YES' : 'NO'));

            $this->calculator->applyNoSwap(
                $cancelTx, $activeTx, $x, $totalItems,
                $config['cancelOngkirMulti'], $config['activeBaseOngkir'],
                $config['priorityOngkir'], $config['multitenantOngkir'],
                $transaksi->isPriority, $activeItems, $cancelItems
            );
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
