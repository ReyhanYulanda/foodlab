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
    ];

    public $appends = ['gambar', 'range'];

    public function getRangeAttribute()
    {
        // $minPrice = $this->calculateMinPriceMenu();
        $minPrice = $this->listMenu()->min('harga');
        return $minPrice;
    }
    public function getIsOpenAttribute()
    {
        $currentTime = now()->format('H:i:s');

        if ($this->jam_buka <= $currentTime && $this->jam_tutup >= $currentTime) {
            return 1; // Tenant sedang buka
        } else {
            return 0; // Tenant sedang tutup
        }
    }

    public function getGambarAttribute()
    {
        return $this->nama_gambar != null ? (asset($this->nama_gambar)) : asset('assets/images/default-image.jpg');
    }

    public function kelola()
    {
        // tenant dapat mengrlola banyak makanan
        return $this->hasMany(MenusKelola::class, 'tenant_id');
    }  
    public function listMenu()
    {
        // tenant memiliki banyak menu
        return $this->hasMany(Menus::class, 'tenant_id')->orderByDesc('isReady');
    }
    // public function listMenu()
    // {
    //     // tenant memiliki banyak menu
    //     return $this->belongsToMany(Menus::class, 'menus_kelola', 'tenant_id', 'menu_id')->withPivot(['harga', 'gambar', 'id', 'isReady', 'nama', 'deskripsi'])->orderByPivot('isReady', 'desc')->wherePivot('deleted_at', null)->as('detail_menu');
    // }

    public function pemilik()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function getNoTeleponAttribute()
    {
        return $this->pemilik->phone;
    }

    public function calculateMinPriceMenu(){
        return $this->listMenu()->min('harga');
    }
}
