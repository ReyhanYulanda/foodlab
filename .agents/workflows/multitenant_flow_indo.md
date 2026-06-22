# Dokumentasi Alur Pesanan Multitenant

> Versi dokumen: 1.0  
> Terakhir diperbarui: 2026-06-19  
> Cakupan: Siklus hidup pesanan multi-tenant end-to-end di Foodlab

---

## 1. Gambaran Umum

Pesanan multitenant memungkinkan pembeli memesan dari **maksimal 2 toko** dalam satu kali checkout. Setiap toko mendapat record `Transaksi` sendiri, tetapi keduanya berbagi `multitenant_id` yang sama. Biaya kirim (`ongkos_kirim`) dibagi secara asimetris: toko pertama membayar tarif dasar (base), toko kedua membayar tarif multitenant yang lebih murah.

### Model Utama

| Model | Tabel | Peran |
|-------|-------|-------|
| `Transaksi` | `transaksi` | Record pesanan inti, satu per toko |
| `TransaksiDetail` | `transaksi_detail` | Rincian per item menu (`menu_id`, `jumlah`, `harga`) |
| `Gedung` | `gedung` | Gedung dengan `ongkir` (dasar) dan `ongkir_multitenant` (tarif toko ke-2) |
| `Ruangan` | `ruangan` | Ruangan → belongsTo `Gedung` |
| `Checkout` | `checkout` | Record pembayaran (khusus QRIS), satu checkout untuk kedua toko |
| `Pengaturan` | `pengaturan` | Penyimpanan konfigurasi key-value |
| `SaldoKoin` | `saldo_koin` | Saldo koin pengguna |
| `TransaksiSaldoKoin` | `transaksi_saldo_koin` | Buku besar transaksi koin |

### Field `Transaksi` yang Relevan dengan Multitenant

| Kolom | Tipe | Kegunaan |
|-------|------|----------|
| `multitenant_id` | int | Mengelompokkan transaksi bersama; `null` = toko tunggal |
| `ongkos_kirim` | int | Biaya kirim untuk toko spesifik ini |
| `biaya_layanan` | int | Biaya layanan per transaksi |
| `total` | int | = `sub_total` + `ongkos_kirim` + `biaya_layanan` |
| `sub_total` | (appended) | Jumlah dari `TransaksiDetail.harga` (harga menu × jumlah) |
| `isAntar` | bool | `1` = kirim, `0` = ambil sendiri |
| `isPriority` | bool | Penanda pesanan prioritas |
| `metode_pembayaran` | enum | `koin`, `qris`, `cod`, `transfer` |
| `status` | string | Status siklus hidup pesanan |

---

## 2. Konfigurasi (Pengaturan)

Pengaturan diambil secara dinamis dari tabel `pengaturan` saat runtime. Nilai default ditunjukkan di bawah:

| Key | Default | Kegunaan |
|-----|---------|----------|
| `biaya_extra` | 500 / 1000 | Biaya tambahan per item jika total item > 10 |
| `ongkos_kirim_prioritas` | 3000 | Biaya prioritas (toko tunggal) |
| `ongkos_kirim_prioritas_multitenant` | 4000 | Biaya prioritas (multitenant, toko pertama saja) |
| `ongkos_kirim_multitenant` | 2000 | Biaya driver tambahan untuk pesanan multitenant |
| `biaya_layanan` | 0 | Biaya layanan dasar per transaksi |
| `biaya_ongkos_kirim` | 10 | Persentase komisi driver (non-multitenant) |
| `timeout_pesanan` | 10 mnt | Batas waktu auto-cancel untuk pesanan tanpa respons |

### Field Gedung

| Kolom | Kegunaan |
|-------|----------|
| `ongkir` | Tarif kirim dasar — dibayar oleh toko **pertama** |
| `ongkir_multitenant` | Tarif kirim lebih murah — dibayar oleh toko **kedua+** |

---

## 3. Siklus Hidup Status Pesanan

```
                 +---> refund_selesai (dibatalkan)
                 |
 pesanan_masuk --+---> pesanan_diproses (toko menerima)
                            |
                      siap_diantar / siap_diambil
                            |
                         diantar (driver dalam perjalanan)
                            |
                         selesai (terkirim)
                            |
                 +---> refund_selesai (refund setelah selesai)
```

**Status yang digunakan dalam pengecekan grup multitenant:**
- **Aktif**: `pesanan_masuk`, `pesanan_diproses`, `siap_diambil`, `siap_diantar`, `diantar`
- **Akhir**: `selesai`, `refund_selesai`, `pesanan_ditolak`

---

## 4. Alur 1: Pembuatan Pesanan

**File:** `app/Http/Controllers/Transaksi/TransaksiController.php::store()` (baris ~467)

### 4.1 Validasi
- Maksimal 2 toko berbeda per pesanan
- Semua menu harus tersedia (`isReady = 1`)
- Toko harus online
- Minimal 1 driver online untuk pesanan kirim

### 4.2 Pengelompokan Menu
Menu dikelompokkan berdasarkan `tenant_id` via `collect($request->menus)->groupBy(...)`.

### 4.3 Pra-Kalkulasi Per Toko

Untuk setiap grup toko:
```php
$isSecondOrMore = count($perTenantCalc) > 0;
$ongkosKirim = $this->getOngkirGedung($ruanganId, $isSecondOrMore);
// Toko pertama: gedung->ongkir
// Toko kedua:   gedung->ongkir_multitenant
```

Jika prioritas DAN toko pertama:
```php
$ongkosKirim += ongkos_kirim_prioritas_multitenant (default 4000)
```

Pra-kalkulasi tiap toko:
```php
totalFinal = totalHargaMenu + ongkosKirim + biayaLayanan
```

### 4.4 Biaya Ekstra Global (Berbasis Item)

Setelah semua toko dihitung, jika total item gabungan > 10:
```php
$totalExtraGlobal = ($totalSemuaItem - 10) * $biayaExtra  // default 500/item
```

Biaya ekstra ini **hanya ditambahkan ke transaksi PERTAMA** yang dibuat (baris ~657).

### 4.5 Pembuatan Transaksi

Setiap toko membuat record `Transaksi` sendiri:
- Semua berbagi `multitenant_id` yang sama
- `multitenant_id` = `MAX(multitenant_id) + 1` (berurutan secara global)
- Transaksi pertama mendapat `$totalExtraGlobal` ditambahkan ke `total` dan `ongkos_kirim`
- Cashback/voucher diterapkan ke **transaksi pertama saja**
- Untuk pembayaran `koin`: `SaldoKoin` pengguna dikurangi per transaksi

### 4.6 Pembayaran QRIS (Multitenant)

Hanya **satu** record `Checkout` dibuat, terikat ke transaksi pertama:
```php
Checkout::create([
    'transaksi_id' => $firstTransaksiId,
    'nominal' => $grandTotal,           // total gabungan kedua toko
    'midtrans_request_id' => $orderId,
    // ... data respons QRIS ...
])
```

### Contoh Perhitungan

```
Toko 1: 7 item × 12.000 = 84.000
Toko 2: 6 item × 12.000 = 72.000
Ongkir dasar (gedung->ongkir) = 5.000
Ongkir multi (gedung->ongkir_multitenant) = 2.000
biaya_extra = 500

Total item = 13 > 10 → biaya ekstra = (13−10) × 500 = 1.500

Transaksi pertama (toko mana pun yang diiterasi lebih dulu):
  total = 84.000 + 5.000 + 1.500 + 0 = 90.500
  ongkos_kirim = 6.500

Transaksi kedua:
  total = 72.000 + 2.000 + 0 = 74.000
  ongkos_kirim = 2.000
```

> **Catatan:** Toko "pertama" ditentukan oleh urutan iterasi array PHP, bukan oleh aturan bisnis. Kedua toko berbagi `ruangan_id` yang sama.

---

## 5. Alur 2: Cancel / Refund

**Titik masuk (semua berisi logika identik):**

| Titik Masuk | File | Baris | Pemicu |
|-------------|------|-------|--------|
| API | `TransaksiController::cancel()` | 1369 | Cancel manual via API |
| Web | `MonitorTransaksiController::postCancel()` | 33 | Cancel manual via web admin |
| Cron | `AutoCancelOrder::handle()` | 21 | Auto-cancel setelah timeout |

### 5.1 Cancel Non-Multitenant

```
1. Hapus record CatatVoucher
2. Kembalikan quantity voucher + cashback
3. Set status → pesanan_ditolak
4. Panggil $transaksi->refundKoin() → tambah total ke SaldoKoin
5. Catat TransaksiSaldoKoin (+)
6. Set status → refund_selesai
7. Notifikasi pengguna via FCM
```

### 5.2 Cancel Multitenant

#### Fase 1: Tandai Dibatalkan
```
1. Set status transaksi → refund_selesai
2. Notifikasi pengguna via FCM
```

#### Fase 2: Periksa Transaksi Aktif Lain
```php
$stillActive = Transaksi::where('multitenant_id', ...)
    ->whereIn('status', ['pesanan_masuk','pesanan_diproses','siap_diambil','siap_diantar','diantar'])
    ->exists();
```

#### Fase 3: Kalkulasi Ulang Pembatalan Pertama (hanya jika `isAntar == 1`)

Tentukan apakah ini **pembatalan pertama** di grup:
```php
$isFirstCancel = (jumlah refund_selesai selain ini) === 0;
```

**Kalkulasi X** (faktor penyesuaian):
```php
$totalItems = $activeItems + $cancelItems;

if ($activeItems <= 10) {
    $x = ($totalItems - 10) * 500;          // Biaya ekstra penuh
} else {
    $x = ($totalItems - 10) * 500           // Total biaya ekstra
       - (($activeItems - 10) * 500);       // Dikurangi bagian toko aktif
}
$x = max($x, 0);
```

**Keputusan SWAP:**
```php
$needSwap = ($cancelTx->ongkos_kirim > $activeTx->ongkos_kirim);
```
Swap diperlukan jika toko yang dibatalkan memiliki ongkir lebih besar (yaitu toko "pertama" saat pembuatan).

**Cabang SWAP:**
```
1. Tukar nilai ongkos_kirim antara cancel dan active
2. Jika totalItems > 10: kurangi ongkir toko aktif sebesar X
3. Hitung ulang cancel: ongkir = cancelOngkirMulti + extraFeeRefundSalahSatu()
4. Hitung ulang total cancel: sub_total + cancelOngkirMulti + extraFeeRefundSalahSatu()
5. Hitung ulang total active: (sub_total + baseOngkir + extraFee(totalItems)) - X
6. Kasus prioritas: jika totalItems ≤ 10, tambahkan multitenantOngkir kembali ke active
```

**Cabang NO SWAP:**
```
1. Jika totalItems > 10: kurangi X dari yang memiliki ongkir lebih besar
2. Hitung ulang cancel: ongkir = cancelOngkirMulti + extraFeeRefundSalahSatu()
3. Hitung ulang total cancel: sub_total + cancelOngkirMulti + extraFeeRefundSalahSatu()
4. Hitung ulang total active: (sub_total + baseOngkir + extraFee(totalItems)) - X
```

**BUKAN Pembatalan Pertama:**
```
Jika totalItems ≤ 10 dan isPriority: kurangi multitenantOngkir dari active
```

#### Fase 4: Pengecekan Semua Direfund
```php
if (!$stillActive) {
    // SEMUA transaksi dibatalkan → refund penuh
    $totalRefund = sum('total') dari semua anggota grup
    // Tambahkan ke SaldoKoin pengguna
    // Kembalikan voucher/cashback
    // Notifikasi pengguna
}
```

### Contoh: Kasus 488 (Toko 2 dibatalkan, Toko 1 aktif)

```
Toko 1 (pertama): 7 item, ongkir=6.500, sub_total=84.000
Toko 2 (kedua):   6 item, ongkir=2.000, sub_total=72.000

Batalkan toko 2:
  cancelTx = toko 2 (ongkir=2.000, item=6)
  activeTx = toko 1 (ongkir=6.500, item=7)
  needSwap = (2.000 > 6.500) = FALSE → NO SWAP
  activeItems = 7 ≤ 10 → X = (13−10)×500 = 1.500
  X dikurangi dari active (ongkir lebih besar):
    active.ongkir = max(6.500 − 1.500, 0) = 5.000

  extraFeeRefundSalahSatu(6, 7, 13):
    keduanya ≤ 10, total > 10 → (13−10)×500 = 1.500

  cancel.ongkir = 2.000 + 1.500 = 3.500
  cancel.total  = 72.000 + 2.000 + 1.500 = 75.500  ✓ (refund)
  active.total  = (84.000 + 5.000 + 1.500) − 1.500 = 89.000  ✓ (bayar)
```

### Contoh: Kasus 487 (Toko 1 dibatalkan, Toko 2 aktif)

```
Toko 2 (pertama): 6 item, ongkir=6.500, sub_total=72.000
Toko 1 (kedua):   7 item, ongkir=2.000, sub_total=84.000

Batalkan toko 1:
  cancelTx = toko 1 (ongkir=2.000, item=7)
  activeTx = toko 2 (ongkir=6.500, item=6)
  needSwap = (2.000 > 6.500) = FALSE → NO SWAP
  activeItems = 6 ≤ 10 → X = (13−10)×500 = 1.500
  cancel ongkir < active ongkir → kurangi X dari active:
    active.ongkir = max(6.500 − 1.500, 0) = 5.000

  extraFeeRefundSalahSatu(7, 6, 13):
    keduanya ≤ 10, total > 10 → (13−10)×500 = 1.500

  cancel.ongkir = 2.000 + 1.500 = 3.500
  cancel.total  = 84.000 + 2.000 + 1.500 = 87.500  ✓ (refund)
  active.total  = (72.000 + 5.000 + 1.500) − 1.500 = 77.000  ✓ (bayar)
```

---

## 6. Alur 3: Driver Pickup & Pengiriman

**File:** `app/Http/Controllers/Masbro/PesananController.php::updateStatus()`

### 6.1 Assign Driver Saat Pickup

Ketika driver menerima (`diantar`) **salah satu** anggota grup multitenant:
- Semua anggota grup mendapat `driver_id` yang sama
- Semua anggota grup mendapat pembaruan status yang sesuai

Pesanan prioritas: driver yang menerima ditugaskan ke SEMUA anggota.
Pesanan non-prioritas: assign driver terjadi ketika SEMUA anggota mencapai `siap_diantar`/`diantar`.

### 6.2 Penyelesaian (`selesai`)

**Logika penyelesaian multitenant:**

```
1. Jika transaksi terkait masih pesanan_masuk/diproses/siap_diantar → DIBLOKIR
   (tidak bisa menyelesaikan satu saat yang lain masih perlu diproses)

2. Jika status transaksi terkait adalah diantar → AUTO-SELESAI transaksi terkait
   (driver yang sama, bukti_pengantaran yang sama, otomatis set ke selesai)

3. Jika status transaksi terkait adalah refund_selesai → lanjut normal
   (toko satunya sudah dibatalkan, yang ini bisa diselesaikan)
```

### 6.3 Refund Parsial Saat Penyelesaian

Saat menyelesaikan pesanan multitenant di mana toko satunya sudah direfund (`refund_selesai`):

```php
// Hitung biaya makanan (sub_total + porsi biaya ekstra)
$hargaMakananRefundTx = refundTx.sub_total (dikurangi ongkir, biaya ekstra)

// Dua skenario dibandingkan:
$totalBayarSemua = semua makanan + base_ongkir + multi_ongkir + prioritas + extraFee(semua item)
$totalBayarSatu  = makanan toko ini + base_ongkir + prioritas + extraFee(item toko ini saja)

$refundAmount = $totalBayarSemua - $totalBayarSatu
// Ini adalah selisih yang dibayar lebih oleh pengguna saat checkout
// → direfund ke SaldoKoin pengguna
```

Ini adalah **mekanisme refund utama** untuk pembatalan multitenant parsial. Alur cancel sendiri hanya mengkalibrasi ulang field `total`/`ongkos_kirim`; refund koin aktual ke pengguna terjadi di sini ketika toko yang bertahan menyelesaikan pesanan.

---

## 7. Alur 4: Pendapatan Driver (Ongkir)

**File:** `PesananController::updateStatus()` (baris ~1174)

### 7.1 Ongkir Driver Multitenant

```php
$ongkirMulti = gedung->ongkir_multitenant  // 2000
$baseOngkir  = gedung->ongkir              // 5000
$priorityOngkir = isPriority ? 4000 : 3000
$pajakPersen = 10  // pajak 10%

// Tentukan status grup
if (isPriority):
    bothSelesai → $baseOngkir + $priorityOngkir + $ongkirMulti
    oneRefund   → $baseOngkir + $priorityOngkir
    bothRefund  → 0
else:
    bothSelesai → $baseOngkir + $ongkirMulti
    oneRefund   → $baseOngkir
    bothRefund  → 0

// Biaya ekstra item: hanya dari transaksi SELESAI (non-refund)
$totalItemsSelesai = jumlah item dari transaksi selesai
if ($totalItemsSelesai > 10):
    $extraOngkir = ($totalItemsSelesai - 10) * 500

$totalOngkir += $extraOngkir
$pajak = 10% * $totalOngkir
$ongkirBersih = $totalOngkir - $pajak  // Pendapatan bersih driver
// Dikreditkan ke SaldoKoin driver
```

### 7.2 Ongkir Driver Non-Multitenant

```php
$potongan = (biaya_ongkos_kirim% / 100) * $ongkirAsli
$ongkirBersih = $ongkirAsli - $potongan
```

---

## 8. Alur 5: Cashback Saat Penyelesaian

**File:** `PesananController::updateStatus()` (baris ~1086)

Cashback dikreditkan **sekali per grup** ketika setidaknya satu transaksi mencapai `selesai` dan tidak ada yang `refund_selesai`:

```php
if (transaksi punya cashback && !bothRefund && atLeastOneSelesai):
    if (cashback belum diberikan untuk multitenant_id ini):
        kredit cashback_amount ke SaldoKoin pengguna
        tandai sebagai sudah diberikan (cek via description LIKE "%multitenant #X%")
```

---

## 9. Auto-Cancel (Cron Job)

**File:** `app/Console/Commands/AutoCancelOrder.php::handle()` (baris 21)

Berjalan secara periodik. Untuk setiap transaksi `pesanan_masuk` tanpa driver, melewati batas timeout:

```
1. Set status → pesanan_ditolak
2. Set catatan_penolakan → pesan auto-cancel
3. Jika multitenant_id:
   → Logika SWAP/kalkulasi ulang yang sama seperti cancel manual
   → Set status → refund_selesai
   → Jika semua anggota grup selesai → refund penuh ke pengguna
   → Kembalikan voucher/cashback
```

---

## 10. Metode Helper

### `getOngkirGedung($ruanganId, $isMultitenant)`
**File:** `TransaksiController.php:1270`

```php
return $isMultitenant
    ? gedung->ongkir_multitenant   // Toko kedua+
    : gedung->ongkir;              // Toko pertama
```

### `extraFee($totalItems)`
**File:** `TransaksiController.php:1851` (juga di MonitorTransaksiController, AutoCancelOrder, PesananController)

```php
if ($totalItems > 10):
    return ($totalItems - 10) * 500;
return 0;
```

### `extraFeeRefundSalahSatu($cancelItems, $activeItems, $totalItems)`
**File:** `TransaksiController.php:1864` (juga di MonitorTransaksiController:690, AutoCancelOrder:444)

Menghitung biaya ekstra yang dapat direfund ketika satu toko membatalkan. **Biaya per item ekstra = 500.**

| Kasus | Kondisi | Rumus |
|-------|---------|-------|
| 1 | cancel>10, active>10, total>10 | `cancelItems × 500` |
| 2 | cancel≤10, active>10, total>10 | `cancelItems × 500` |
| 3 | cancel≤10, active≤10, total>10 | `(totalItems − 10) × 500` |
| 4 | cancel>10, active≤10, total>10 | `(totalItems − 10) × 500` |

**Logika matematika:** `max(0, totalItems−10)×500 − max(0, activeItems−10)×500` — selisih antara biaya ekstra awal dan yang seharusnya dibayar toko yang bertahan.

> **Catatan:** Kasus 3 sebelumnya bug (mengembalikan `cancelItems × 500`), diperbaiki 2026-06-19.

---

## 11. Referensi File Kunci

| File | Kegunaan |
|------|----------|
| `app/Http/Controllers/Transaksi/TransaksiController.php` | Pembuatan pesanan (store), cancel (cancel), metode helper |
| `app/Http/Controllers/Web/Transaksi/MonitorTransaksiController.php` | Cancel admin web (logika cancel identik) |
| `app/Console/Commands/AutoCancelOrder.php` | Auto-cancel cron (logika cancel identik) |
| `app/Http/Controllers/Masbro/PesananController.php` | Alur driver: pickup, kirim, selesai, pendapatan, refund |
| `app/Models/Transaksi.php` | Model pesanan, relasi, atribut tambahan |
| `app/Models/Gedung.php` | Gedung dengan `ongkir` dan `ongkir_multitenant` |
| `app/Models/Pengaturan.php` | Konfigurasi key-value |
| `app/Models/Ruangan.php` | Ruangan → belongsTo Gedung |

---

## 12. Checklist Onboarding untuk Developer Baru

- [ ] Pahami pembagian `Gedung.ongkir` vs `Gedung.ongkir_multitenant`
- [ ] Pahami bahwa "toko pertama" = urutan iterasi, bukan logika bisnis
- [ ] Pahami kasus `extraFeeRefundSalahSatu` dan verifikasi dengan data uji
- [ ] Semua logika cancel harus dijaga sinkron di 3 file (API, Web, Cron)
- [ ] Saat memodifikasi cancel: uji skenario SWAP dan NO SWAP
- [ ] Driver mendapat ongkir hanya dari transaksi `selesai` (bukan yang direfund)
- [ ] QRIS multitenant: satu checkout, digunakan bersama kedua transaksi
- [ ] Cashback: diberikan sekali per grup, bukan per transaksi
