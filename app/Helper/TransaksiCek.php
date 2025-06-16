<?php

namespace App\Helper;

use App\Models\Transaksi;
use App\Models\User;
use App\Response\ResponseApi;
use Illuminate\Support\Facades\Request;
use Kreait\Firebase\Auth\CreateActionLink\ApiRequest;

class TransaksiCek
{
    private $user;
    private $request;

    public function __construct($user, $request)
    {
        $this->user = $user;
        $this->request = $request;
    }
    public function metodePembayaran()
    {
        if ($this->request->metode_pembayaran == 'transfer') {
            if (!$this->user->can('transfer')) {
                return false;
            }
        }
        if ($this->request->metode_pembayaran == 'cod') {
            if (!$this->user->can('cod')) {
                return false;
            }
        }
        return true;
    }

    public function antar()
    {
        $masbro = User::role('masbro')->first();
        if ($this->request->isAntar) {
            if (!$this->user->can('antar')) {
                return false;
            }
            if (!$masbro->isOnline) {
                return false;
            }
        }

        return true;
    }

    public static function generateKodePemesanan($transaksiId)
    {
        // Generate 3 huruf kapital acak
        $huruf = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 3));

        // Generate 2 angka acak (00–99)
        $angka = str_pad(random_int(0, 99), 2, '0', STR_PAD_LEFT);

        // Gabungkan
        return $huruf . $angka;
    }
}
