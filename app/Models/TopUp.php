<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopUp extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'request_id',
        'midtrans_request_id',
        'nominal',
        'biaya_midtrans',
        'biaya_ubsima',
        'total_biaya_admin',
        'total_bayar_user',
        'status_bayar',
        'kode_bayar',
        'tgl_bayar',
        'tgl_akhir_tagihan',
        'isTf',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
