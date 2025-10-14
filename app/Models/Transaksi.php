<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        'isPriority',
        'metode_pembayaran',
        'tenant_id',
        'catatan',
        'driver_id',
        'kode_pemesanan',
        'catatan_lokasi_pengantaran',
        'catatan_penolakan',
        'bukti_pengantaran',
        'cashback_amount',
        'voucher_id'
    ];

    protected $appends = ['sub_total', 'gedung', 'nama_ruangan', 'nama_pembeli', 'nama_tenant', 'order_id', 'nama_driver', 'foto_driver'];
    protected $hidden = ['driver'];

    public function getQrUrlAttribute()
    {
        return $this->metode_pembayaran === 'qris'
            ? ($this->attributes['qr_url'] ?? null)
            : null;
    }

    public function getExpiryAttribute()
    {
        return $this->metode_pembayaran === 'qris'
            ? ($this->attributes['expiry'] ?? null)
            : null;
    }

    public function getBiayaAdminAttribute()
    {
        return $this->metode_pembayaran === 'qris'
            ? ($this->attributes['biaya_admin'] ?? null)
            : null;
    }
    protected function serializeDate(DateTimeInterface $date)
    {
        return Carbon::instance($date)->setTimezone('Asia/Jakarta')->toIso8601String();
    }

    public function getOrderIdAttribute()
    {
        $tanggal = $tanggal = Carbon::parse($this->created_at)->format("Ymd");
        return "ORDER" . $tanggal . "000{$this->id}";
    }

    public function getSubTotalAttribute()
    {
        return (int)$this->listTransaksiDetail()->sum(DB::raw('harga'));
    }

    public function getGedungAttribute()
    {
        return @$this->ruangan->gedung->nama;
    }

    public function getNamaTenantAttribute()
    {
        $firstTenant = $this->listTransaksiDetail
            ->map(function ($detail) {
                return optional($detail->menus->tenants)->nama_tenant;
            })
            ->filter()
            ->unique()
            ->first();

        return $firstTenant ?? '-';
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

    public function ruangan()
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id', 'id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id', 'id');
    }

    public function refundKoin()
    {
        if ($this->status === 'refund_selesai') {
            throw new \Exception("Transaksi sudah direfund sebelumnya.");
        }

        $saldo = SaldoKoin::firstOrCreate(['user_id' => $this->user_id]);
        $saldo->jumlah += $this->total;
        $saldo->save();
    }

    public function getNamaDriverAttribute()
    {
        return $this->driver ? $this->driver->name : null;
    }

    public function getFotoDriverAttribute()
    {
        return $this->driver ? $this->driver->image : null;
    }

    public function checkout()
    {
        return $this->hasOne(Checkout::class, 'transaksi_id', 'id');
    }
    public function voucher()
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

    public function catat_voucher()
    {
        return $this->hasOne(CatatVoucher::class, 'transaksi_id', 'id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenants::class, 'tenant_id', 'id');
    }

    public function scopeForTenant($query, $tenantUserId)
    {
        return $query->whereHas('listTransaksiDetail.menus.tenants', function ($q) use ($tenantUserId) {
            $q->where('user_id', $tenantUserId);
        });
    }
    public function scopeForUserOrTenant($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId) // 🧍 user pembeli
                ->orWhereHas('listTransaksiDetail.menus.tenants', function ($q2) use ($userId) {
                    $q2->where('user_id', $userId); // 🏪 tenant pemilik toko
                })
                ->orWhere('driver_id', $userId); // 🛵 driver pengantar
        });
    }
}
