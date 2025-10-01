<?php

namespace App\Models;

use App\Http\Middleware\Tenant;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Silber\Bouncer\Database\HasRolesAndAbilities;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'fcm_token',
        'isOnline',
        'manual_offline',
        'manual_override',
        "phone",
        "image",
        "google_id"
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function fcmTokens()
    {
        return $this->hasMany(FcmToken::class);
    }

    public function rolesKw()
    {
        return $this->belongsToMany(Role::class, 'model_has_roles', 'model_id', 'role_id');
    }

    public function tenant()
    {
        return  $this->belongsTo(Tenants::class, 'id', 'user_id');
    }

    public function transaksiKoin()
    {
        return $this->hasMany(TransaksiSaldoKoin::class);
    }

    public function koin()
    {
        return $this->hasOne(SaldoKoin::class);
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }
    public function transaksis()
    {
        return $this->hasMany(Transaksi::class, 'tenant_id', 'id');
    }
    public function vouchers()
    {
        return $this->hasMany(Voucher::class);
    }

    public function getSaldoAttribute()
    {
        return $this->koin ? $this->koin->jumlah : 0;
    }

    public function driverDetail()
    {
        return $this->hasOne(DriverDetail::class);
    }
}
