<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashierDetail extends Model
{
    use HasFactory;
    
    protected $table = 'cashiers_detail';

    protected $fillable = [
        'cashier_id',
        'menu_id',
        'jumlah',
        'harga',
        'catatan',
    ];

    public $appends = ['nama_tenant'];

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

    protected function serializeDate(\DateTimeInterface $date)
    {
        return Carbon::instance($date)
            ->timezone('Asia/Jakarta')
            ->format('Y-m-d\TH:i:sP');
    }

    public function getNamaTenantAttribute()
    {
        return $this->menu->tenant->nama_tenant;
    }
}
