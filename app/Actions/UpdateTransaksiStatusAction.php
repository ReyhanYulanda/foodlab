<?php

namespace App\Actions;

use App\Models\Transaksi;

class UpdateTransaksiStatusAction
{
    /**
     * Update status transaksi + status detail jika bukan transfer.
     */
    public function execute(Transaksi $transaksi, string $newStatus): Transaksi
    {
        $transaksi->status = $newStatus;
        $transaksi->save();

        if ($transaksi->metode_pembayaran != 'transfer') {
            $transaksi->listTransaksiDetail()->update([
                'status' => $transaksi->status,
            ]);
        }

        return $transaksi;
    }
}
