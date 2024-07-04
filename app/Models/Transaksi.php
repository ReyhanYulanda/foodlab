<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Transaksi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'transaksi';
    protected $fillable = [
        'user_id',
        'status',
        'ruangan_id',
        'total',
        'ongkos_kirim',
        'biaya_layanan',
        'isAntar',
        'metode_pembayaran',
        'catatan'
    ];

    public $appends = ['sub_total', 'gedung', 'nama_ruangan', "nama_pembeli", "order_id"];

    protected function serializeDate(DateTimeInterface $date)
    {
        return Carbon::instance($date)->setTimezone('Asia/Jakarta')->toIso8601String();
    }

    public function getOrderIdAttribute(){
        $tanggal = $tanggal = Carbon::parse($this->created_at)->format("Ymd");
        return "ORDER".$tanggal."000{$this->id}";
    }

    public function getSubTotalAttribute()
    {
        return (int)$this->listTransaksiDetail()->sum(DB::raw('harga * jumlah'));
    }
    public function getGedungAttribute()
    {
        return @$this->ruangan->gedung->nama;
    }
    public function getNamaPembeliAttribute()
    {
        return @$this->user()->first()->name;
    }
    public function getNamaRuanganAttribute()
    {
        return @$this->ruangan->nama_ruangan;
    }

    public function listTransaksiDetail()
    {
        return $this->hasMany(TransaksiDetail::class, 'transaksi_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function ruangan(){
        return $this->belongsTo(Ruangan::class,'ruangan_id','id');
    }
}
