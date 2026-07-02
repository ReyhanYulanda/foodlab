# 📦 Dokumentasi Modul Foodlab Backend (Laravel)

> **Foodlab** — Platform food delivery/kantin digital dengan 4 role utama: **Admin**, **Pembeli**, **Tenant/Penjual**, dan **Driver (Masbro)**.

---

## 🔐 AUTHENTICATION

| # | Modul | Controller | Endpoint | Method | Deskripsi |
|---|-------|-----------|----------|--------|-----------|
| 1 | Login | `Api\AuthController` | `POST /api/login` | POST | Login dengan email & password |
| 2 | Register | `Api\AuthController` | `POST /api/register` | POST | Registrasi akun baru |
| 3 | Logout | `Api\AuthController` | `POST /api/logout` | POST | Logout & hapus token Sanctum |
| 4 | Google OAuth | `Api\AuthController` | `POST /api/auth/google` | POST | Login/register via Google |
| 5 | Google Redirect | — | `GET /api/auth/google/redirect` | GET | Redirect ke halaman Google OAuth |
| 6 | Email Verification | `Api\Auth\EmailVerificationController` | `GET /api/verify-email/{id}/{hash}` | GET | Verifikasi email (signed URL) |
| 7 | Send Verification | `Auth\EmailVerificationNotificationController` | `POST /api/send-email-verification` | POST | Kirim ulang email verifikasi |
| 8 | Forgot Password | `Auth\PasswordResetLinkController` | `POST /api/forgot-password` | POST | Kirim link reset password |
| 9 | Reset Password | `Auth\NewPasswordController` | `POST /api/reset-password` | POST | Set password baru |
| 10 | Web Auth Pages | `Auth\*Controller` (8 file) | `/login`, `/register`, `/forgot-password`, dsb. | GET/POST | Halaman auth web (Laravel Breeze/Jetstream) |

---

## 👤 USER / PEMBELI

| # | Modul | Controller | Endpoint | Method | Deskripsi |
|---|-------|-----------|----------|--------|-----------|
| 11 | Profil User | `User\UserController` | `GET /api/auth` | GET | Ambil data user terautentikasi |
| 12 | Update Profil | `User\UserController` | `POST /api/update-user` | POST | Update data profil user |
| 13 | Update FCM Token | `User\UserController` | `PUT /api/update-fcm-token` | PUT | Update token FCM untuk push notification |
| 14 | Katalog Tenant | `Tenant\TenantController` | `GET /api/tenants`, `GET /api/katalog/tenants` | GET | List semua tenant/kantin |
| 15 | Detail Tenant | `Tenant\TenantController` | `GET /api/tenants/{TenantId}` | GET | Detail spesifik tenant |
| 16 | Menu Tenant | `Tenant\TenantController` | `GET /api/menus/{id}` | GET | Ambil menu milik tenant tertentu |
| 17 | Buat Pesanan | `Transaksi\TransaksiController` | `POST /api/order` | POST | Buat pesanan baru (order makanan) |
| 18 | Detail Pesanan | `Transaksi\TransaksiController` | `POST /api/order/detail` | POST | Tambah detail item pesanan |
| 19 | Riwayat Pesanan | `Transaksi\TransaksiController` | `GET /api/order/user` | GET | Riwayat pesanan user (aktif) |
| 20 | Semua Riwayat | `Transaksi\TransaksiController` | `GET /api/order/user/all/transaksi` | GET | Semua riwayat transaksi user |
| 21 | Detail Riwayat | `Transaksi\TransaksiController` | `GET /api/order/user/{id}` | GET | Detail satu transaksi user berdasarkan ID |
| 22 | Batalkan Pesanan | `Transaksi\TransaksiController` | `POST /api/order/cancel/{id}` | POST | Batalkan pesanan yang sedang berlangsung |
| 23 | Update Status | `User\TransaksiUserController` | `PUT /api/order/{id}` | PUT | Update status transaksi oleh user |
| 24 | Chat ke Tenant | `Transaksi\TransaksiController` | `POST /api/transaksi/{id}/chat` | POST | Kirim pesan chat ke tenant |
| 25 | Lihat Chat Tenant | `Transaksi\TransaksiController` | `GET /api/transaksi/{id}/chat/tenant-buyer` | GET | Lihat chat dari tenant |
| 26 | Lihat Chat Driver | `Transaksi\TransaksiController` | `GET /api/transaksi/{id}/chat/driver-buyer` | GET | Lihat chat dari driver |
| 27 | Cek Saldo Koin | `Api\SaldoKoin\SaldoKoinController` | `GET /api/saldo` | GET | Cek saldo koin user |
| 28 | Riwayat Saldo | `Api\SaldoKoin\SaldoKoinController` | `GET /api/saldo/riwayat` | GET | Riwayat mutasi saldo koin |
| 29 | Rating Moods | `RatingController` | `GET /api/rating-moods` | GET | Ambil daftar mood rating (emoji) |
| 30 | Beri Rating | `RatingController` | `POST /api/ratings` | POST | Kirim rating setelah pesanan selesai |
| 31 | Cek Versi Rating | `RatingController` | `GET /api/ratings/check-version` | GET | Cek versi rating (mencegah spam rating) |
| 32 | List Cashback | `Api\Cashback\CashbackController` | `GET /api/list/cashback/active` | GET | Daftar cashback yang sedang aktif |
| 33 | List Voucher | `Api\Voucher\VoucherController` | `GET /api/list/voucher/active` | GET | Daftar voucher yang sedang aktif |
| 34 | Klaim Voucher | `Api\Voucher\VoucherController` | `POST /api/get/voucher/{referral_code}` | POST | Klaim voucher via kode referral |
| 35 | TopUp Saldo | `Transaksi\TransaksiController` | `POST /api/transaksi/topup` | POST | TopUp saldo koin |
| 36 | Cek TopUp | `Transaksi\TransaksiController` | `GET /api/transaksi/get-top-up/{kodeBayar}` | GET | Cek status topup berdasarkan kode bayar |
| 37 | TopUp Midtrans | `Transaksi\TransaksiController` | `POST /api/transaksi/topup/midtrans` | POST | Buat transaksi topup via Midtrans Snap |
| 38 | Cek TopUp Midtrans | `Transaksi\TransaksiController` | `GET /api/transaksi/get/topup/midtrans/{id}` | GET | Cek status topup Midtrans |
| 39 | Webhook Midtrans | `Transaksi\TransaksiController` | `POST /api/order/callback` | POST | Callback Midtrans (unsecured endpoint) |
| 40 | Midtrans Callback | `Transaksi\TransaksiController` | `POST /api/midtrans/callback` | POST | Callback Midtrans backend handler |
| 41 | Push to Ubisma | `Transaksi\TransaksiController` | `POST /api/push-to-ubisma` | POST | Push data transaksi ke sistem Ubisma (eksternal) |
| 42 | Ruangan | `Api\RuanganController` | `GET /api/ruangan` | GET | List ruangan yang tersedia |
| 43 | Pengaturan | `Api\PengaturanController` | `GET /api/pengaturan` | GET | Ambil konfigurasi aplikasi global |

---

## 🏪 TENANT / PENJUAL

| # | Modul | Controller | Endpoint | Method | Deskripsi |
|---|-------|-----------|----------|--------|-----------|
| 44 | Dashboard Tenant | `Kelola\TenantController` | `GET /api/tenant` | GET | Data dashboard tenant |
| 45 | Tambah Menu | `Kelola\TenantController` | `POST /api/tenant/menu` | POST | Tambah menu baru |
| 46 | Update Menu | `Kelola\TenantController` | `POST /api/tenant/menu/{id}` | POST | Edit menu yang sudah ada |
| 47 | Hapus Menu | `Kelola\TenantController` | `DELETE /api/tenant/menu/{id}` | DELETE | Hapus menu |
| 48 | Riwayat Transaksi | `Kelola\TenantController` | `GET /api/tenant/history-transaksi-tenant` | GET | Riwayat transaksi tenant |
| 49 | Penghasilan | `Transaksi\TransaksiController` | `GET /api/tenant/penghasilan-transaksi-tenant` | GET | Total penghasilan tenant |
| 50 | Interupsi Sibuk | `Kelola\TenantController` | `POST /api/tenant/interupt` | POST | Tandai tenant sedang sibuk/tidak melayani |
| 51 | Order Tenant | `Transaksi\TransaksiController` | `GET /api/order/tenant` | GET | List pesanan masuk ke tenant |
| 52 | Profil Tenant | `Kelola\Tenant\ProfileTenantController` | `GET /api/tenant/profile-tenant` | GET | Lihat profil tenant |
| 53 | Update Profil | `Kelola\Tenant\ProfileTenantController` | `POST /api/tenant/profile-tenant` | POST | Update profil tenant |
| 54 | Kasir - Buat | `CashierController` | `POST /api/tenant/kasir` | POST | Buat transaksi kasir (POS offline) |
| 55 | Kasir - Riwayat | `CashierController` | `GET /api/tenant/kasir/riwayat` | GET | Riwayat transaksi kasir |
| 56 | Kasir - Detail | `CashierController` | `GET /api/tenant/kasir/riwayat/{id}` | GET | Detail transaksi kasir |
| 57 | Kasir - Update | `CashierController` | `PUT /api/tenant/kasir/{id}` | PUT | Update transaksi kasir |
| 58 | Kasir - Hapus | `CashierController` | `DELETE /api/tenant/kasir/{id}` | DELETE | Hapus transaksi kasir |
| 59 | Kasir - Proses Order | `Kelola\TenantOrderController` | `PUT /api/tenant/kasir/order/{id}` | PUT | Proses order oleh kasir |
| 60 | Tenant Order List | `Kelola\TenantOrderController` | `GET /api/tenant/order` | GET | List order tenant (manajemen pesanan) |
| 61 | Tenant Order Update | `Kelola\TenantOrderController` | `PUT /api/tenant/order/{id}` | PUT | Update status order tenant |

---

## 🛵 DRIVER / MASBRO (PENGANTAR)

| # | Modul | Controller | Endpoint | Method | Deskripsi |
|---|-------|-----------|----------|--------|-----------|
| 62 | Order Masbro | `Masbro\PesananController` | `GET /api/masbro/order` | GET | List order yang bisa diambil driver |
| 63 | Ambil/Update Order | `Masbro\PesananController` | `PUT\|POST /api/masbro/order/{id}` | PUT/POST | Ambil order atau update status pengantaran |
| 64 | Ping ke Pembeli | `Transaksi\TransaksiController` | `POST /api/masbro/ping-to-buyer/{id}` | POST | Kirim notifikasi posisi driver ke pembeli |
| 65 | Rekening Pencairan | `User\UserController` | `POST /api/masbro/post/rekening-pencairan` | POST | Input rekening untuk pencairan dana |
| 66 | Data Driver | `User\UserController` | `GET /api/masbro/get/data-driver` | GET | Ambil data profil driver |
| 67 | Leaderboard | `Transaksi\TransaksiController` | `GET /api/leaderboard/driver` | GET | Leaderboard performa driver |
| 68 | Online Driver | `Transaksi\TransaksiController` | `GET /api/order/driver` | GET | List driver yang sedang online |
| 69 | Order Masbro (alt) | `Transaksi\TransaksiController` | `GET /api/order/masbro` | GET | List order untuk driver (rute alternatif) |

---

## 🛡️ ADMIN (WEB PANEL)

| # | Modul | Controller | Endpoint | Method | Deskripsi |
|---|-------|-----------|----------|--------|-----------|
| 70 | Dashboard | `Web\DashboardController` | `GET /dashboard` | GET | Dashboard utama admin |
| 71 | Menu Konfigurasi | `Web\Konfigurasi\MenuController` | `resource /menu` | CRUD | CRUD menu navigasi sidebar admin |
| 72 | Role | `Web\Konfigurasi\RoleController` | `resource /role` | CRUD | Manajemen role (admin, tenant, masbro, dll) |
| 73 | Permission | `Web\Konfigurasi\PermissionController` | `resource /permission` | CRUD | Manajemen permission akses |
| 74 | User Management | `Web\UserController` | `resource /user` | CRUD | Manajemen semua user (CRUD lengkap) |
| 75 | Tenant Management | `Web\TenantController` | `resource /tenant` | CRUD | Manajemen data tenant/kantin |
| 76 | Menu Kategori | `MenuKategori` | `resource /menu-kategori` | CRUD | Kategori menu (makanan, minuman, dll) |
| 77 | Ruangan | `Web\RuanganController` | `resource /ruangan` | CRUD | Manajemen data ruangan/kantin |
| 78 | Gedung | `Web\GedungController` | `resource /gedung` | CRUD | Manajemen data gedung |
| 79 | Rating Mood | `Web\RatingMoodController` | `resource /rating_mood` | CRUD | Konfigurasi mood rating (emoji) |
| 80 | Cashback | `Web\CashbackController` | `resource /cashback` | CRUD | Konfigurasi program cashback |
| 81 | Pembayaran | `Web\PembayaranController` | `resource /pembayaran` | CRUD | Manajemen metode pembayaran |
| 82 | Pengaturan | `Web\Konfigurasi\PengaturanController` | `resource /pengaturan` | CRUD | Konfigurasi sistem global |
| 83 | Notifikasi | `NotifikasiController` | `GET\|POST /notifikasi/kirim` | GET/POST | Kirim notifikasi massal ke user |
| 84 | List Driver Aktif | `ListAktifDriverController` | `GET /list-driver` | GET | Monitoring driver yang sedang online |
| 85 | Set Driver Offline | `ListAktifDriverController` | `POST /list-driver/{user}/set-offline` | POST | Paksa driver offline |
| 86 | Saldo Koin | `Web\SaldoKoin\SaldoKoinController` | `resource /saldo_koin` | CRUD | Manajemen saldo koin semua user |
| 87 | Ekspor Saldo Koin | `Web\SaldoKoin\SaldoKoinController` | `GET /saldo_koin/csv/export` | GET | Ekspor data saldo koin ke CSV |
| 88 | Riwayat Saldo User | `Web\SaldoKoin\SaldoKoinController` | `GET /saldo_koin/riwayat/{user_id}` | GET | Riwayat mutasi saldo per user |
| 89 | Transfer Coin | `Web\SaldoKoin\TransferCoinController` | `GET\|POST /transfer_coin` | GET/POST | Transfer koin antar user oleh admin |
| 90 | Transaksi Tenant | `Web\Transaksi\TransaksiTenantController` | `GET /transaksi_tenant` | GET | Monitoring transaksi tenant |
| 91 | Detail Transaksi | `Web\Transaksi\TransaksiTenantController` | `GET /transaksi_tenant/{id}` | GET | Detail transaksi tenant |
| 92 | Pesanan by Transaksi | `Web\Transaksi\TransaksiTenantController` | `GET /pesanan-transaksi/{id}` | GET | Lihat pesanan dalam suatu transaksi |
| 93 | Ekspor Transaksi | `Web\Transaksi\TransaksiTenantController` | `GET /export-transaksi-tenant*` | GET | Ekspor CSV transaksi (3 varian: umum, jasa, rekap) |
| 94 | Transaksi Driver | `Web\Transaksi\TransaksiDriverController` | `GET /transaksi_driver` | GET | Monitoring transaksi driver |
| 95 | Payout Driver | `Web\Transaksi\TransaksiDriverController` | `GET /transaksi_driver/payout` | GET | Halaman pencairan dana driver |
| 96 | Ekspor Payout | `Web\Transaksi\TransaksiDriverController` | `POST /transaksi_driver/payout/export` | POST | Ekspor CSV data payout driver |
| 97 | Detail Transaksi Driver | `Web\Transaksi\TransaksiDriverController` | `GET /transaksi_driver/{id}` | GET | Detail transaksi driver |
| 98 | Detail Pencairan | `Web\Transaksi\TransaksiDriverController` | `GET /transaksi_driver/pencairan/{id}` | GET | Detail pencairan dana driver |
| 99 | Ekspor Driver CSV | `Web\Transaksi\TransaksiDriverController` | `GET /transaksi-driver/export` | GET | Ekspor CSV semua transaksi driver |
| 100 | Monitor Voucher | `Web\MonitorVoucherController` | `GET /monitor_voucher` | GET | Monitoring voucher & pemakaiannya |
| 101 | Monitor Pesanan | `Web\Transaksi\MonitorTransaksiController` | `GET /monitor_pesanan` | GET | Live monitoring semua pesanan |
| 102 | Cancel Pesanan | `Web\Transaksi\MonitorTransaksiController` | `POST /monitor_pesanan/cancel/{id}` | POST | Batalkan pesanan oleh admin |
| 103 | Reset Driver | `Web\Transaksi\MonitorTransaksiController` | `POST /monitor/pesanan/{id}/reset-driver` | POST | Reset/ganti driver pada pesanan |
| 104 | Status Pesanan | `Web\Transaksi\StatusPesananTransaksiTenantController` | `GET /status_pesanan_transaksi` | GET | Monitoring status pesanan tenant |
| 105 | User Review | `Web\UserReviewController` | `GET /user_review` | GET | Monitoring rating & review dari user |
| 106 | Ekspor Review | `Web\UserReviewController` | `GET /user_review/export` | GET | Ekspor CSV data review user |
| 107 | Keuangan | `Web\KeuanganController` | `GET /keuangan` | GET | Ringkasan keuangan semua role |
| 108 | Pesanan (Web) | `Web\PesananController` | `GET /pesanan` | GET | Halaman monitoring pesanan di web |
| 109 | Katalog (Web) | `Web\KatalogController` | `GET /katalog` | GET | Halaman katalog di web panel |
| 110 | Data | `Web\DataController` | `GET /data` | GET | Halaman data/statistik |
| 111 | API Docs | `Web\ApiDocsController` | `GET /api-docs/{file?}` | GET | Dokumentasi API (file viewer) |
| 112 | Pembayaran Transfer | `Web\PembayaranController` | `POST /pembayaran/transfer` | POST | Proses transfer pembayaran |
| 113 | Admin Transfer Koin | `Api\SaldoKoin\SaldoKoinController` | `POST /api/admin/coin/tf/backdoor` | POST | Transfer koin backdoor (admin API) |
| 114 | Update Menu Web | `Kelola\TenantController` | `POST /menu/{id}` | POST | Update menu via web panel |

---

## 🗄️ MODEL / ENTITAS

| # | Model | File Path | Tabel (estimasi) | Deskripsi |
|---|-------|-----------|------------------|-----------|
| 1 | `User` | `app/Models/User.php` | `users` | User sistem (admin, tenant, masbro, pembeli) |
| 2 | `Tenants` | `app/Models/Tenants.php` | `tenants` | Data tenant/penjual/kantin |
| 3 | `Menus` | `app/Models/Menus.php` | `menus` | Menu makanan/minuman milik tenant |
| 4 | `MenusKelola` | `app/Models/MenusKelola.php` | `menus_kelola` | Relasi menu yang dikelola tenant |
| 5 | `Transaksi` | `app/Models/Transaksi.php` | `transaksi` | Transaksi/order utama |
| 6 | `TransaksiDetail` | `app/Models/TransaksiDetail.php` | `transaksi_detail` | Detail item dalam satu transaksi |
| 7 | `TopUp` | `app/Models/TopUp.php` | `top_up` | Riwayat topup saldo koin |
| 8 | `Cashier` | `app/Models/Cashier.php` | `cashier` | Transaksi kasir (POS offline) |
| 9 | `CashierDetail` | `app/Models/CashierDetail.php` | `cashier_detail` | Detail item transaksi kasir |
| 10 | `SaldoKoin` | `app/Models/SaldoKoin.php` | `saldo_koin` | Saldo koin per user |
| 11 | `TransaksiSaldoKoin` | `app/Models/TransaksiSaldoKoin.php` | `transaksi_saldo_koin` | Log mutasi saldo koin |
| 12 | `Voucher` | `app/Models/Voucher.php` | `voucher` | Data voucher/diskon |
| 13 | `CatatVoucher` | `app/Models/CatatVoucher.php` | `catat_voucher` | Log pemakaian voucher oleh user |
| 14 | `Cashback` | `app/Models/Cashback.php` | `cashback` | Konfigurasi program cashback |
| 15 | `Rating` | `app/Models/Rating.php` | `rating` | Rating & review dari pembeli |
| 16 | `RatingMood` | `app/Models/RatingMood.php` | `rating_mood` | Konfigurasi mood/emoji rating |
| 17 | `DetailMoodRating` | `app/Models/DetailMoodRating.php` | `detail_mood_rating` | Detail per mood dalam rating |
| 18 | `ChatMessage` | `app/Models/ChatMessage.php` | `chat_messages` | Pesan chat buyer-tenant-driver |
| 19 | `Gedung` | `app/Models/Gedung.php` | `gedung` | Data gedung |
| 20 | `Ruangan` | `app/Models/Ruangan.php` | `ruangan` | Data ruangan dalam gedung |
| 21 | `Kategori` | `app/Models/Kategori.php` | `kategori` | Kategori menu (makanan, minuman, snack) |
| 22 | `Pengaturan` | `app/Models/Pengaturan.php` | `pengaturan` | Konfigurasi sistem global |
| 23 | `DriverDetail` | `app/Models/DriverDetail.php` | `driver_detail` | Detail driver (SIM, kendaraan, dll) |
| 24 | `FcmToken` | `app/Models/FcmToken.php` | `fcm_tokens` | Token FCM untuk push notification |
| 25 | `Device` | `app/Models/Device.php` | `devices` | Device user yang terdaftar |
| 26 | `Checkout` | `app/Models/Checkout.php` | `checkout` | Session checkout sementara |
| 27 | `Role` | `app/Models/Role.php` | `roles` | Role user (Spatie/Permission) |
| 28 | `Permission` | `app/Models/Permission.php` | `permissions` | Permission akses (Spatie/Permission) |
| 29 | `Konfigurrasi\Menu` | `app/Models/Konfigurrasi/Menu.php` | `konfigurasi_menu` | Menu sidebar admin |
| 30 | `Konfigurrasi\MenuPermission` | `app/Models/Konfigurrasi/MenuPermission.php` | `menu_permission` | Mapping menu ke permission |

---

## ⚙️ SERVICES (Business Logic)

| # | Service | File Path | Deskripsi |
|---|---------|-----------|-----------|
| 1 | `Midtrans` | `app/Services/Midtrans.php` | Integrasi pembayaran Midtrans (Snap API) |
| 2 | `Transaksi` | `app/Services/Transaksi.php` | Business logic transaksi/order |
| 3 | `Firebases` | `app/Services/Firebases.php` | Push notification via Firebase Cloud Messaging |
| 4 | `TenantService` | `app/Services/Kelola/TenantService.php` | Business logic manajemen tenant & menu |
| 5 | `TenantOrderService` | `app/Services/Kelola/TenantOrderService.php` | Business logic pengelolaan order tenant |
| 6 | `GedungService` | `app/Services/Admin/GedungService.php` | Business logic manajemen gedung |
| 7 | `RuanganService` | `app/Services/Admin/RuanganService.php` | Business logic manajemen ruangan |

---

## 📊 RINGKASAN

| Domain | Jumlah Endpoint | Jumlah Controller | Jumlah Model |
|--------|----------------|-------------------|--------------|
| Authentication | 10 | 9 | — |
| User / Pembeli | 33 | 8 | — |
| Tenant / Penjual | 18 | 4 | — |
| Driver / Masbro | 8 | 3 | — |
| Admin Web Panel | 45 | 25 | — |
| **TOTAL** | **114** | **49** | **30** |

### Role Sistem

| Role | Slug | Deskripsi |
|------|------|-----------|
| Admin | `admin` | Pengelola sistem penuh, akses web panel |
| Tenant | `tenant` | Penjual makanan/minuman di kantin |
| Masbro | `masbro` | Driver pengantar pesanan |
| KDH | `kdh` | Role tambahan (akses web panel terbatas) |
| User/Pembeli | (default) | Pembeli/pengguna aplikasi mobile |

---

## ⚠️ Catatan

- **Missing File:** `app/Http/Controllers/SendNotificationController.php` di-import di `routes/api.php:24` tapi **tidak ditemukan** di filesystem. Setiap route yang mereferensi controller ini akan menyebabkan fatal error.
- **Auth Guard:** API menggunakan `auth:sanctum` + `verified` + `request.logger` middleware.
- **Role Middleware:** Menggunakan `role:admin|tenant|masbro` untuk membatasi akses endpoint.
- **Payment Gateway:** Midtrans (Snap API) untuk pembayaran dan topup.
- **Real-time:** Menggunakan WebSocket (Laravel Broadcasting) untuk notifikasi real-time.
- **Push Notification:** Firebase Cloud Messaging (FCM) untuk notifikasi ke mobile app.
