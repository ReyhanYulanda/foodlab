<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransaksiSaldoKoin extends Model
{
    use HasFactory;

    protected $table = 'transaksi_saldo_koin';

    protected $fillable = ['user_id', 'jumlah', 'tipe', 'deskripsi'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
