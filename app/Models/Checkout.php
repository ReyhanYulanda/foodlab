<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Checkout extends Model
{
    use HasFactory;

    protected $table = 'checkout';

    protected $fillable = [
        'user_id',
        'transaksi_id',
        'nominal',
        'biaya_midtrans',
        'biaya_ubisma',
        'total_biaya_admin',
        'total_bayar_user',
        'status_bayar',
        'midtrans_request_id',
        'kode_bayar',
        'tgl_bayar',
        'tgl_akhir_tagihan',
        'isTf',
    ];

    protected $casts = [
        'tgl_bayar' => 'datetime',
        'tgl_akhir_tagihan' => 'datetime',
        'isTf' => 'boolean',
    ];

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
