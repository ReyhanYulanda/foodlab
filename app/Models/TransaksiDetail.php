<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransaksiDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'transaksi_detail';
    protected $fillable = [
        'menu_id',
        'transaksi_id',
        'jumlah',
        'catatan',
        'harga',
        'status'
    ];

    public $appends = ['nama_menu', 'kategori_menu'];

    public function getNamaMenuAttribute(){
        return @$this->menus->nama;
    }
    public function getKategoriMenuAttribute(){
        return @$this->menus->kategori->nama;
    }

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class, 'transaksi_id');
    }

    public function menus(){
        return $this->belongsTo(Menus::class, 'menu_id', 'id')->withTrashed(true);
    }
    // public function menusKelola(){
    //     return $this->belongsTo(MenusKelola::class, 'menus_kelola_id', 'id');
    // }
}
