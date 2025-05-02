<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class TransaksiSaldoKoin extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'transaksi_saldo_koin';

    protected $fillable = ['user_id', 'jumlah', 'tipe', 'deskripsi'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->timezone('Asia/Jakarta')->toDateTimeString();
    }
    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->timezone('Asia/Jakarta')->toDateTimeString();
    }
}
