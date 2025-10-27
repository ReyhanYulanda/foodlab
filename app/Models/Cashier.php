<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cashier extends Model
{
    use HasFactory;
    
    protected $table = 'cashiers';

    protected $fillable = [
        'user_id',
        'kode_pemesanan',
        'total',
    ];

    /**
     * Relasi ke user (kasir/pembuat transaksi)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi ke detail transaksi kasir
     */
    public function details()
    {
        return $this->hasMany(CashierDetail::class, 'cashier_id');
    }
}
