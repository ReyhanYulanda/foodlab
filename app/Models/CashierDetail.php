<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashierDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'cashier_id',
        'menu_id',
        'jumlah',
        'harga',
        'catatan',
    ];

    /**
     * Relasi ke transaksi kasir induk
     */
    public function cashier()
    {
        return $this->belongsTo(Cashier::class);
    }

    /**
     * Relasi ke menu
     */
    public function menu()
    {
        return $this->belongsTo(Menus::class, 'menu_id');
    }
}
