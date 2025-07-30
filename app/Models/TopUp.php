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
        'nominal',
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
