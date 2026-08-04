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
        $saldo = SaldoKoin::firstOrCreate(['user_id' => $transaksi->user_id]);
        $saldo->jumlah += $transaksi->total;
        $saldo->save();

        TransaksiSaldoKoin::create([
            'user_id' => $transaksi->user_id,
            'jumlah' => $transaksi->total,
            'tipe' => 'masuk',
            'deskripsi' => 'Refund pesanan #' . $transaksi->id,
        ]);
    }

    public function restoreVoucher(Transaksi $transaksi): void
    {
        CatatVoucher::where('transaksi_id', $transaksi->id)->delete();

        if ($transaksi->cashback_amount > 0 && $transaksi->voucher_id) {
            $voucher = $transaksi->voucher;

            if ($voucher) {
                $voucher->increment('quantity');

                if ($voucher->cashback) {
                    $voucher->cashback->increment('quantity');
                }

                Log::info("Voucher #{$voucher->id} dikembalikan karena refund transaksi #{$transaksi->id}");
            }
        }
    }

    public function refundFullMultitenant(Transaksi $transaksi): void
    {
        $totalRefund = Transaksi::where('multitenant_id', $transaksi->multitenant_id)->sum('total');

        $saldo = SaldoKoin::firstOrCreate(['user_id' => $transaksi->user_id]);
        $saldo->jumlah += $totalRefund;
        $saldo->save();

        TransaksiSaldoKoin::create([
            'user_id' => $transaksi->user_id,
            'jumlah' => $totalRefund,
            'tipe' => 'masuk',
            'deskripsi' => 'Refund pesanan multitenant #' . $transaksi->multitenant_id,
        ]);

        $transaksiDenganVoucher = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
            ->whereNotNull('voucher_id')
            ->first();

        if ($transaksiDenganVoucher && $transaksiDenganVoucher->voucher_id) {
            $voucher = $transaksiDenganVoucher->voucher;

            if ($voucher) {
                CatatVoucher::where('transaksi_id', $transaksiDenganVoucher->id)->delete();

                $voucher->increment('quantity');

                if ($voucher->cashback) {
                    $voucher->cashback->increment('quantity');
                }

                Log::info("Voucher #{$voucher->id} dikembalikan otomatis karena semua transaksi multitenant #{$transaksi->multitenant_id} refund (auto cancel).");
            }
        }

        Log::info("Refund penuh multitenant #{$transaksi->multitenant_id} sebesar {$totalRefund} berhasil dilakukan.");
    }
}
