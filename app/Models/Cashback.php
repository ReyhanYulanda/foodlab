<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cashback extends Model
{
    use HasFactory;

    protected $table = 'cashbacks';

    protected $fillable = [
        'value',
        'quantity',
        'is_valid',
        'referral_code',
        'minimal_beli',
        'max_cashback',
        'max_used',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'is_valid' => 'boolean',
        'value' => 'float',
    ];

    public function vouchers()
    {
        return $this->hasMany(Voucher::class, 'cashback_id');
    }
}
