<?php

namespace App\Services\Cashier\Helpers;

class KodePemesananGenerator
{
    public static function generate(): string
    {
        // Generate 3 huruf kapital acak
        $huruf = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 3));

        // Generate 2 angka acak (00–99)
        $angka = str_pad(random_int(0, 99), 2, '0', STR_PAD_LEFT);

        // Gabungkan
        return $huruf . $angka;
    }
}
