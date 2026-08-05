<?php

namespace App\Repositories;

use App\Models\Transaksi;
use App\Models\SaldoKoin;
use App\Models\TransaksiSaldoKoin;
use App\Models\CatatVoucher;
use Illuminate\Support\Facades\Log;

class ExpiredTransactionRepository
{
    public function refundKoin(Transaksi $transaksi): void
    {
        $this->creditSaldo($transaksi->user_id, $transaksi->total, 'Refund pesanan #' . $transaksi->id);
        $this->restoreVoucherFor($transaksi);
    }

    public function restoreVoucher(Transaksi $transaksi): void
    {
        $this->restoreVoucherFor($transaksi);
    }

    public function refundFullMultitenant(Transaksi $transaksi): void
    {
        $totalRefund = Transaksi::where('multitenant_id', $transaksi->multitenant_id)->sum('total');

        $this->creditSaldo($transaksi->user_id, $totalRefund, 'Refund pesanan multitenant #' . $transaksi->multitenant_id);

        $transaksiDenganVoucher = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
            ->whereNotNull('voucher_id')
            ->first();

        if ($transaksiDenganVoucher && $transaksiDenganVoucher->voucher_id) {
            $voucher = $transaksiDenganVoucher->voucher;

            if ($voucher) {
                CatatVoucher::where('transaksi_id', $transaksiDenganVoucher->id)->delete();
                $this->incrementVoucherAndCashback($voucher);

                Log::info("Voucher #{$voucher->id} dikembalikan otomatis karena semua transaksi multitenant #{$transaksi->multitenant_id} refund (auto cancel).");
            }
        }

        Log::info("Refund penuh multitenant #{$transaksi->multitenant_id} sebesar {$totalRefund} berhasil dilakukan.");
    }

    private function creditSaldo(int $userId, int $amount, string $description): void
    {
        $saldo = SaldoKoin::firstOrCreate(['user_id' => $userId]);
        $saldo->jumlah += $amount;
        $saldo->save();

        TransaksiSaldoKoin::create([
            'user_id'    => $userId,
            'jumlah'     => $amount,
            'tipe'       => 'masuk',
            'deskripsi'  => $description,
        ]);
    }

    private function restoreVoucherFor(Transaksi $transaksi): void
    {
        CatatVoucher::where('transaksi_id', $transaksi->id)->delete();

        if ($transaksi->cashback_amount > 0 && $transaksi->voucher_id) {
            $voucher = $transaksi->voucher;

            if ($voucher) {
                $this->incrementVoucherAndCashback($voucher);

                Log::info("Voucher #{$voucher->id} dikembalikan karena refund transaksi #{$transaksi->id}");
            }
        }
    }

    private function incrementVoucherAndCashback($voucher): void
    {
        $voucher->increment('quantity');

        if ($voucher->cashback) {
            $voucher->cashback->increment('quantity');
        }
    }
}
