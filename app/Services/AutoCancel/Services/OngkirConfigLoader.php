<?php

namespace App\Services\AutoCancel\Services;

use App\Models\Transaksi;
use App\Models\Pengaturan;

class OngkirConfigLoader
{
    public function load(Transaksi $transaksi, Transaksi $cancelTx, Transaksi $activeTx): array
    {
        return [
            'cancelOngkirMulti'  => $cancelTx->ruangan->gedung->ongkir_multitenant ?? 0,
            'activeBaseOngkir'   => $activeTx->ruangan->gedung->ongkir ?? 0,
            'priorityOngkir'     => $this->getPriorityOngkir($transaksi),
            'multitenantOngkir'  => Pengaturan::where('nama', 'ongkos_kirim_multitenant')->value('nilai') ?? 2000,
        ];
    }

    private function getPriorityOngkir(Transaksi $transaksi): int
    {
        $name    = $transaksi->isPriority ? 'ongkos_kirim_prioritas_multitenant' : 'ongkos_kirim_prioritas';
        $default = $transaksi->isPriority ? 4000 : 3000;

        return Pengaturan::where('nama', $name)->value('nilai') ?? $default;
    }
}
