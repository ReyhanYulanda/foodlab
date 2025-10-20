<?php

use App\Events\NotifyUserWhenTransaksiUpdated;
use App\Http\Controllers\Api\Auth\EmailVerificationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Cashback\CashbackController;
use App\Http\Controllers\Api\PengaturanController;
use App\Http\Controllers\Api\RuanganController;
use App\Http\Controllers\Kelola\TenantController as KelolaTenantController;
use App\Http\Controllers\Kelola\TenantOrderController;
use App\Http\Controllers\Masbro\PesananController;
use App\Http\Controllers\Tenant\TenantController;
use App\Http\Controllers\Transaksi\TransaksiController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\Api\SaldoKoin\SaldoKoinController;
use App\Http\Controllers\Api\Voucher\VoucherController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\CashierController;
use App\Models\Transaksi;
use App\Http\Controllers\Kelola\Tenant\ProfileTenantController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\SendNotificationController;
use App\Http\Controllers\User\TransaksiUserController;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;

Route::post('menu/{id}', [KelolaTenantController::class, 'updateMenuWeb']);

Route::middleware('auth:sanctum', 'verified', 'request.logger')->group(function () {
    Route::post('/transaksi/topup', [TransaksiController::class, 'storeTopUp']);
    Route::get('/transaksi/get-top-up/{kodeBayar}', [TransaksiController::class, 'getTopUp']);
    Route::post('/tenant/menucoba/{id}', [KelolaTenantController::class, 'updateMenu']);
    Route::post('/transaksi/topup/midtrans', [TransaksiController::class, 'midtransTopUp']);
    Route::get('/transaksi/get/topup/midtrans/{midtransRequestId}', [TransaksiController::class, 'midtransGetTopUp']);
    Route::post('/transaksi/{transaksiId}/chat', [TransaksiController::class, 'sendMessage'])
        ->middleware('transaksi.chat_protect');
    Route::get('/transaksi/{transaksiId}/chat/tenant-buyer', [TransaksiController::class, 'getMessageTenantToBuyer']);
    Route::get('/transaksi/{transaksiId}/chat/driver-buyer', [TransaksiController::class, 'getMessageDriverToBuyer']);
    Route::get('/leaderboard/driver', [TransaksiController::class, 'getLeaderboardDriver']);
    Route::get('/list/cashback/active', [CashbackController::class, 'getListCashback']);
    Route::get('/list/voucher/active', [VoucherController::class, 'getListVoucher']);
    Route::post('/get/voucher/{referral_code}', [VoucherController::class, 'postGetVoucher']);

    Route::get('/auth', [UserController::class, 'index']);
    Route::post('/update-user', [UserController::class, 'update']);
    // USER 
    Route::get('/katalog/tenants', [TenantController::class, 'getAll']);
    Route::get('/katalog/tenants/{TenantId}', [TenantController::class, 'getSpecificTenant']);
    Route::get('/tenants', [TenantController::class, 'getAll']);
    Route::get('/tenants/{TenantId}', [TenantController::class, 'getSpecificTenant']);
    Route::get('/menus/{id}', [TenantController::class, 'getMenusById']);
    Route::get('/order/user', [TransaksiController::class, 'orderUser']);
    Route::get('/order/user/{id}', [TransaksiController::class, 'orderUserById']);
    Route::post('/order', [TransaksiController::class, 'store']);
    Route::get('/order/driver', [TransaksiController::class, 'getOnlineDriver']);
    Route::put('/order/{id}', [TransaksiUserController::class, 'updateStatusTransaksi']);
    Route::post('/order/cancel/{id}', [TransaksiController::class, 'cancel']);
    Route::get('/order/tenant', [TransaksiController::class, 'orderTenant']);
    Route::get('/order/masbro', [TransaksiController::class, 'orderMasbro']);
    Route::post('/order/detail', [TransaksiController::class, 'store'])->name('');
    Route::get('/ruangan', [RuanganController::class, 'index']);
    Route::get('/rating-moods', [RatingController::class, 'getMoods']);
    Route::post('/ratings', [RatingController::class, 'store']);
    Route::get('/ratings/check-version', [RatingController::class, 'checkVersion']);

    // SALDO KOIN USER
    Route::get('/saldo', [SaldoKoinController::class, 'cekSaldo']);
    Route::get('/saldo/riwayat', [SaldoKoinController::class, 'riwayatTransaksi']);

    Route::prefix('admin')->middleware(['role:admin'])->name('api.admin.')->group(
        function () {
            Route::post('/coin/tf/backdoor', [SaldoKoinController::class, 'transferCoin']);
        }
    );
    // TENANT
    Route::prefix('tenant')->middleware(['role:tenant'])->name('api.tenant.')->group(function () {
        // MENU
        Route::get('/', [KelolaTenantController::class, 'index']);
        Route::post('/menu', [KelolaTenantController::class, 'storeMenu']);
        Route::post('/menu/{id}', [KelolaTenantController::class, 'updateMenu']);
        Route::delete('/menu/{id}', [KelolaTenantController::class, 'destroyMenu']);

        // KASIR
        Route::post('/kasir', [CashierController::class, 'store']);
        Route::get('/kasir/riwayat', [CashierController::class, 'getHistory']);
        Route::get('/kasir/riwayat/{id}', [CashierController::class, 'getHistoryById']);
        Route::put('/kasir/{id}', [CashierController::class, 'update']);
        Route::delete('/kasir/{id}', [CashierController::class, 'destroy']);

        // TENANT ORDER
        Route::get('/order', [TenantOrderController::class, 'index']);
        Route::put('/order/{id}', [TenantOrderController::class, 'update']);

        // SHOWTRANSAKSI
        Route::get('/history-transaksi-tenant', [KelolaTenantController::class, 'showHistoryTransaksiTenant']);
        Route::get('/penghasilan-transaksi-tenant', [TransaksiController::class, 'getPenghasilanTenant']);

        // PROFILE TENANT
        Route::get('/profile-tenant', [ProfileTenantController::class, 'show']);
        Route::post('/profile-tenant', [ProfileTenantController::class, 'update']);
        Route::post('/interupt', [KelolaTenantController::class, 'interuptBusy']);
    });

    // MASBRO
    Route::prefix('masbro')->middleware(['role:masbro'])->name('api.masbro.')->group(function () {
        Route::get('/order', [PesananController::class, 'index']);
        Route::match(['put', 'post'], '/order/{transaksiId}', [PesananController::class, 'update']);
        Route::post('/ping-to-buyer/{transaksiId}', [TransaksiController::class, 'pushNotificationDriverToBuyer']);
        Route::post('/post/rekening-pencairan', [UserController::class, 'postRekeningPencairan']);
        Route::get('/get/data-driver', [UserController::class, 'getDataDriver']);
    });

    Route::put('/update-fcm-token', [UserController::class, 'updateFcmToken']);
    Route::post('/order/cancel/{id}', [TransaksiController::class, 'cancel']);
});
Route::post('/push-to-ubisma', [TransaksiController::class, 'pushToUbisma']);
Route::post('/order/callback', [TransaksiController::class, 'webHookMidtrans']);

Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/auth/google', [AuthController::class, 'googleLogin']);
Route::get('/auth/google/redirect', function () {
    return Socialite::driver('google')->redirect();
});

Route::get('/pengaturan', [PengaturanController::class, 'index']);

Route::get('/test-web-socket', function () {
    $transaksi = Transaksi::first();
    broadcast(new NotifyUserWhenTransaksiUpdated($transaksi));
});

Route::get('/verify-email/{id}/{hash}', [\App\Http\Controllers\Api\Auth\EmailVerificationController::class, 'verify'])
    ->middleware('signed')
    ->name('verification.verify');

Route::post('/forgot-password', [PasswordResetLinkController::class, 'passwordResetAPI']);
Route::post('/reset-password', [NewPasswordController::class, 'newPasswordAPI']);
Route::post('/send-email-verification', [EmailVerificationNotificationController::class, 'send']);
Route::post('/midtrans/callback', [TransaksiController::class, 'handleCallback']);
