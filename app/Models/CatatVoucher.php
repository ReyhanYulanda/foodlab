<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatatVoucher extends Model
{
    use HasFactory;

    protected $table = 'catat_vouchers';

    protected $fillable = [
        'user_id',
        'transaksi_id',
        'voucher_id',
        'quantity_voucher',
        'cashback_amount',
    ];

    /**
     * Relasi ke User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi ke Transaksi
     */
    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class);
    }

    /**
     * Relasi ke Voucher
     */
    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }
}
