<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Tenants extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'tenants';

    protected $fillable = [
        'nama_tenant',
        'nama_kavling',
        'nama_gambar',
        'jam_buka',
        'jam_tutup',
        'user_id',
        'no_rekening_toko',
        'no_rekening_pribadi',
        'is_busy',
        'busy_until',
        'is_interupt'
    ];

    public $appends = ['gambar', 'range', 'transaksi_berhasil'];

    public function getTransaksiBerhasilAttribute()
    {
        return TransaksiDetail::whereHas('menus', function ($query) {
            $query->where('tenant_id', $this->id);
        })
            ->where('status', 'selesai')
            ->count();
    }

    public function getRangeAttribute()
    {
        $minPrice = $this->listMenu()->min('harga');
        return $minPrice;
    }

    public function getGambarAttribute()
    {
        return $this->nama_gambar != null ? (asset($this->nama_gambar)) : asset('assets/images/default-image.jpg');
    }

    public function kelola()
    {
        return $this->hasMany(MenusKelola::class, 'tenant_id');
    }
    public function listMenu()
    {
        return $this->hasMany(Menus::class, 'tenant_id')->orderByDesc('isReady');
    }

    public function pemilik()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function getNoTeleponAttribute()
    {
        return $this->pemilik->phone;
    }

    public function getIsOnlineAttribute()
    {
        return $this->pemilik->isOnline;
    }

    public function calculateMinPriceMenu()
    {
        return $this->listMenu()->min('harga');
    }
}
