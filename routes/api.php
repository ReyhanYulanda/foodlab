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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::post('menu/{id}', [KelolaTenantController::class, 'updateMenuWeb']);


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth', [UserController::class, 'index']);
    Route::put('/update-user', [UserController::class, 'update']);
    // USER
    Route::get('/katalog/tenants', [TenantController::class, 'getAll']);
    Route::get('/katalog/tenants/{TenantId}', [TenantController::class, 'getSpecificTenant']);
    // Route::post('/order', [TransaksiController::class, 'store']);
    // Route::post('/order/detail', [TransaksiController::class, 'store'])->name('');
    // Route::get('/ruangan', [RuanganController::class, 'index']);
    Route::get('/tenants', [TenantController::class, 'getAll']);
    Route::get('/tenants/{TenantId}', [TenantController::class, 'getSpecificTenant']);
    Route::get('/order/user', [TransaksiController::class, 'orderUser']);
    Route::post('/order', [TransaksiController::class, 'store']);
    Route::post('/order/cancel/{id}', [TransaksiController::class, 'cancel']);
    Route::get('/order/tenant', [TransaksiController::class, 'orderTenant']);
    Route::get('/order/masbro', [TransaksiController::class, 'orderMasbro']);
    // Route::get('/order/riwayat/2', [TransaksiController::class, 'orderUser']);
    Route::post('/order/detail', [TransaksiController::class, 'store'])->name('');
    Route::get('/ruangan', [RuanganController::class, 'index']);

    // dari rei
    // saldo
    Route::get('/saldo', [SaldoKoinController::class, 'cekSaldo']);
    Route::post('/saldo/tambah', [SaldoKoinController::class, 'tambahSaldo']);
    Route::post('/saldo/kurang', [SaldoKoinController::class, 'kurangiSaldo']);
    Route::get('/saldo/riwayat', [SaldoKoinController::class, 'riwayatTransaksi']);

    // TENANT
    Route::prefix('tenant')->middleware(['role:tenant'])->name('api.tenant.')->group(function () {
        Route::get('/', [KelolaTenantController::class, 'index']);
        Route::post('/menu', [KelolaTenantController::class, 'storeMenu']);
        Route::post('/menu/{id}', [KelolaTenantController::class, 'updateMenu']);
        Route::delete('/menu/{id}', [KelolaTenantController::class, 'destroyMenu']);

        Route::get('/order', [TenantOrderController::class, 'index']);
        Route::put('/order/{id}', [TenantOrderController::class, 'update']);
    });

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

// Route::resource('user', UserController::class);

// Midtrans
// Route::post('/getSnapToken', function () {
//     // Set your Merchant Server Key
//     \Midtrans\Config::$serverKey = 'SB-Mid-server-VVST_NSHtMMRxUO6Wm768Ejv';
//     // Set to Development/Sandbox Environment (default). Set to true for Production Environment (accept real transaction).
//     \Midtrans\Config::$isProduction = false;

//     $params = array(
//         'transaction_details' => array(
//             'order_id' => rand(),
//             'gross_amount' => 10000,
//         ),
//         'customer_details' => array(
//             'first_name' => 'budi',
//             'last_name' => 'pratama',
//             'email' => 'budi.pra@example.com',
//             'phone' => '08111222333',
//         ),
//     );

//     $response = \Midtrans\Snap::createTransaction($params);

//     return response()->json($response);
// });
