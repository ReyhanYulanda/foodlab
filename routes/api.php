<?php

use App\Events\NotifyUserWhenTransaksiUpdated;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PengaturanController;
use App\Http\Controllers\Api\RuanganController;
use App\Http\Controllers\Kelola\TenantController as KelolaTenantController;
use App\Http\Controllers\Kelola\TenantOrderController;
use App\Http\Controllers\Masbro\PesananController;
use App\Http\Controllers\Tenant\TenantController;
use App\Http\Controllers\Transaksi\TransaksiController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\Api\SaldoKoin\SaldoKoinController;
use App\Models\Transaksi;
use App\Http\Controllers\Kelola\Tenant\ProfileTenantController;
use App\Http\Controllers\User\TransaksiUserController;
use Illuminate\Support\Facades\Route;

Route::post('menu/{id}', [KelolaTenantController::class, 'updateMenuWeb']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth', [UserController::class, 'index']);
    Route::post('/update-user', [UserController::class, 'update']);
    // USER 
    Route::get('/katalog/tenants', [TenantController::class, 'getAll']);
    Route::get('/katalog/tenants/{TenantId}', [TenantController::class, 'getSpecificTenant']);
    Route::get('/tenants', [TenantController::class, 'getAll']);
    Route::get('/tenants/{TenantId}', [TenantController::class, 'getSpecificTenant']);
    Route::get('/order/user', [TransaksiController::class, 'orderUser']);
    Route::post('/order', [TransaksiController::class, 'store']);
    Route::put('/order/{id}', [TransaksiUserController::class, 'updateStatusTransaksi']);
    Route::post('/order/cancel/{id}', [TransaksiController::class, 'cancel']);
    Route::get('/order/tenant', [TransaksiController::class, 'orderTenant']);
    Route::get('/order/masbro', [TransaksiController::class, 'orderMasbro']);
    Route::post('/order/detail', [TransaksiController::class, 'store'])->name('');
    Route::get('/ruangan', [RuanganController::class, 'index']);

    // SALDO KOIN USER
    Route::get('/saldo', [SaldoKoinController::class, 'cekSaldo']);
    Route::get('/saldo/riwayat', [SaldoKoinController::class, 'riwayatTransaksi']);

    // TENANT
    Route::prefix('tenant')->middleware(['role:tenant'])->name('api.tenant.')->group(function () {
        // MENU
        Route::get('/', [KelolaTenantController::class, 'index']);
        Route::post('/menu', [KelolaTenantController::class, 'storeMenu']);
        Route::post('/menu/{id}', [KelolaTenantController::class, 'updateMenu']);
        Route::delete('/menu/{id}', [KelolaTenantController::class, 'destroyMenu']);

        // TENANT ORDER
        Route::get('/order', [TenantOrderController::class, 'index']);
        Route::put('/order/{id}', [TenantOrderController::class, 'update']);

        // SHOWTRANSAKSI
        Route::get('/history-transaksi-tenant', [KelolaTenantController::class, 'showHistoryTransaksiTenant']);

        // PROFILE TENANT
        Route::get('/profile-tenant', [ProfileTenantController::class, 'show']);
        Route::post('/profile-tenant', [ProfileTenantController::class, 'update']);
    });

    // MASBRO
    Route::prefix('masbro')->middleware(['role:masbro'])->name('api.masbro.')->group(function () {
        Route::get('/order', [PesananController::class, 'index']);
        Route::put('/order/{transaksiId}', [PesananController::class, 'update']);
    });

    Route::put('/update-fcm-token', [UserController::class, 'updateFcmToken']);
});
Route::post('/order/callback', [TransaksiController::class, 'webHookMidtrans']);
Route::post('/order/cancel/{id}', [TransaksiController::class, 'cancel']);

Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/pengaturan', [PengaturanController::class, 'index']);

Route::get('/test-web-socket', function(){
    $transaksi = Transaksi::first();
    broadcast(new NotifyUserWhenTransaksiUpdated($transaksi));
});