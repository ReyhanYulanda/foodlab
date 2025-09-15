<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;

    protected $table = 'vouchers';

    protected $fillable = [
        'user_id',
        'cashback_id',
        'quantity',
    ];

    public function cashback()
    {
        return $this->belongsTo(Cashback::class, 'cashback_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
