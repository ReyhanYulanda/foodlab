<?php

use App\Http\Controllers\Kelola\TenantController as KelolaTenantController;
use App\Http\Controllers\MenuKategori;
use App\Http\Controllers\Web\DataController;
use App\Http\Controllers\Web\GedungController;
use App\Http\Controllers\Web\KatalogController;
use App\Http\Controllers\Web\KeuanganController;
use App\Http\Controllers\Web\Konfigurasi\MenuController;
use App\Http\Controllers\Web\Konfigurasi\PengaturanController;
use App\Http\Controllers\Web\Konfigurasi\PermissionController;
use App\Http\Controllers\Web\Konfigurasi\RoleController;
use App\Http\Controllers\Web\PembayaranController;
use App\Http\Controllers\Web\PesananController;
use App\Http\Controllers\Web\RuanganController as WebRuanganController;
use App\Http\Controllers\Web\TenantController;
use App\Http\Controllers\Web\UserController;
use App\Http\Controllers\Web\SaldoKoin\SaldoKoinController;
use App\Http\Controllers\Web\Transaksi\StatusPesananTransaksiTenantController;
use App\Http\Controllers\Web\Transaksi\TransaksiDriverController;
use App\Http\Controllers\Web\Transaksi\TransaksiTenantController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\EmailVerificationController;
use App\Http\Controllers\ListAktifDriverController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\Web\RatingMoodController;
use App\Http\Controllers\Web\CashbackController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\MonitorVoucherController;
use App\Http\Controllers\Web\SaldoKoin\TransferCoinController;
use App\Http\Controllers\Web\Transaksi\MonitorTransaksiController;
use App\Http\Controllers\Web\UserReviewController;

Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed'])
    ->name('verification.verify');

Route::get('/email-verified', function () {
    return view('auth.email-verified');
})->name('email.verified');


Route::get('/', function () {
    return redirect()->route('login');
});



Route::middleware(['shared', 'auth', 'role:tenant|kdh|admin'])->group(function () {

    Route::get('/welcome', function () {
        return view('pages.welcome.index');
    })->name('welcome');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('menu', MenuController::class);
    Route::resource('role', RoleController::class);
    Route::get('role/{id}/permission', [RoleController::class, 'removePermission'])->name('role.destroy.permission');
    Route::resource('permission', PermissionController::class);
    Route::resource('tenant', TenantController::class);
    Route::resource('user', UserController::class);
    Route::resource('menu-kategori', MenuKategori::class);
    Route::resource('ruangan', WebRuanganController::class);
    Route::resource('gedung', GedungController::class);
    Route::resource('rating_mood', RatingMoodController::class);
    Route::resource('cashback', CashbackController::class);
    Route::resource('pembayaran', PembayaranController::class);
    Route::resource('pengaturan', PengaturanController::class);

    Route::get('/notifikasi/kirim', [NotifikasiController::class, 'index'])->name('notifikasi.index');
    Route::post('/notifikasi/kirim', [NotifikasiController::class, 'kirim'])->name('notifikasi.kirim');

    Route::get('/list-driver', [ListAktifDriverController::class, 'index'])->name('list-driver.index');
    Route::post('/list-driver/{user}/set-offline', [ListAktifDriverController::class, 'setOffline'])->name('list-driver.setOffline');

    Route::post('/pembayaran/transfer', [PembayaranController::class, 'transfer'])->name('pembayaran.transfer');

    Route::get('/data', [DataController::class, 'index']);
    Route::get('/pesanan', [PesananController::class, 'index']);
    Route::get('/keuangan', [KeuanganController::class, 'index']);
    Route::get('/katalog', [KatalogController::class, 'index']);

    Route::post('menu/{id}', [KelolaTenantController::class, 'updateMenuWeb']);

    Route::get('/saldo_koin', [SaldoKoinController::class, 'index'])->name('saldoKoin.index');
    Route::get('/saldo_koin/create', [SaldoKoinController::class, 'create'])->name('saldoKoin.create');
    Route::post('/saldo_koin', [SaldoKoinController::class, 'store'])->name('saldoKoin.store');
    Route::get('/saldo_koin/riwayat/{user_id}', [SaldoKoinController::class, 'riwayatTransaksi'])->name('saldoKoin.riwayat');

    Route::get('/transaksi_tenant', [TransaksiTenantController::class, 'transaksiTenant'])->name('transaksi.tenant');
    Route::get('/transaksi_tenant/{id}', [TransaksiTenantController::class, 'detailTransaksiTenant'])->name('detail.transaksi.tenant');
    Route::get('/pesanan-transaksi/{id}', [TransaksiTenantController::class, 'getPesananByTransaksi']);
    Route::get('/export-transaksi-tenant', [TransaksiTenantController::class, 'exportCsv'])->name('export.transaksi.tenant');
    Route::get('/export-transaksi-tenant-jasa', [TransaksiTenantController::class, 'exportCsvJasa'])->name('export.transaksi.tenant.jasa');
    Route::get('/export-transaksi-tenant-rekap', [TransaksiTenantController::class, 'exportCsvRekap'])->name('export.transaksi.tenant.rekap');

    Route::get('/transaksi_driver', [TransaksiDriverController::class, 'transaksiDriver'])->name('transaksi.driver');
    Route::get('/transaksi_driver/{id}', [TransaksiDriverController::class, 'detailTransaksiDriver'])->name('detail.transaksi.driver');
    Route::get('/transaksi_driver/pencairan/{id}', [TransaksiDriverController::class, 'detailPencairanTransaksiDriver'])->name('detail.pencairan.transaksi.driver');
    // Route::get('/export-transaksi-tenant', [TransaksiDriverController::class, 'exportCsv'])->name('export.transaksi.driver');

    Route::get('/monitor_voucher', [MonitorVoucherController::class, 'monitorVoucher'])->name('monitor.voucher');

    Route::get('/monitor_pesanan', [MonitorTransaksiController::class, 'monitorPesanan'])->name('monitor.pesanan');
    Route::post('/monitor_pesanan/cancel/{id}', [MonitorTransaksiController::class, 'postCancel'])->name('monitor.pesanan.cancel');

    Route::get('/transfer_coin', [TransferCoinController::class, 'index'])->name('transfer.coin.index');
    Route::post('/transfer_coin', [TransferCoinController::class, 'transferCoin'])->name('transfer.coin.store');

    Route::get('/status_pesanan_transaksi', [StatusPesananTransaksiTenantController::class, 'statuspesanantransaksi'])->name('status.pesanan.transaksi.tenant');

    Route::get('/user_review', [UserReviewController::class, 'index'])->name('user_review.index');
});

require __DIR__ . '/auth.php';
