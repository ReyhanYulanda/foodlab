# Multitenant Order Flow Documentation

> Document version: 1.0  
> Last updated: 2026-06-19  
> Scope: End-to-end multi-tenant order lifecycle in Foodlab

---

## 1. Overview

Multi-tenant orders allow a buyer to order from **up to 2 tenants** in a single checkout. Each tenant gets its own `Transaksi` record, but both share a common `multitenant_id`. The shipping cost (`ongkos_kirim`) is split asymmetrically between tenants: the first pays the base rate, the second pays a cheaper multi-tenant rate.

### Key Models

| Model | Table | Role |
|-------|-------|------|
| `Transaksi` | `transaksi` | Core order record, one per tenant |
| `TransaksiDetail` | `transaksi_detail` | Per-menu-item breakdown (`menu_id`, `jumlah`, `harga`) |
| `Gedung` | `gedung` | Building with `ongkir` (base) and `ongkir_multitenant` (cheaper 2nd rate) |
| `Ruangan` | `ruangan` | Room → belongs to `Gedung` |
| `Checkout` | `checkout` | Payment record (QRIS only), single checkout covers both tenants |
| `Pengaturan` | `pengaturan` | Key-value config store |
| `SaldoKoin` | `saldo_koin` | User's coin balance |
| `TransaksiSaldoKoin` | `transaksi_saldo_koin` | Coin transaction ledger |

### Key `Transaksi` Fields (Multi-tenant relevant)

| Column | Type | Purpose |
|--------|------|---------|
| `multitenant_id` | int | Groups transactions together; `null` = single tenant |
| `ongkos_kirim` | int | Shipping cost for this specific tenant |
| `biaya_layanan` | int | Service fee per transaction |
| `total` | int | = `sub_total` + `ongkos_kirim` + `biaya_layanan` |
| `sub_total` | (appended) | Sum of `TransaksiDetail.harga` (menu price × qty) |
| `isAntar` | bool | `1` = delivery, `0` = pickup |
| `isPriority` | bool | Priority order flag |
| `metode_pembayaran` | enum | `koin`, `qris`, `cod`, `transfer` |
| `status` | string | Order lifecycle state |

---

## 2. Configuration (Pengaturan)

Settings fetched dynamically from `pengaturan` table at runtime. Defaults shown below:

| Key | Default | Purpose |
|-----|---------|---------|
| `biaya_extra` | 500 / 1000 | Extra fee per item when total items > 10 |
| `ongkos_kirim_prioritas` | 3000 | Priority surcharge (single-tenant) |
| `ongkos_kirim_prioritas_multitenant` | 4000 | Priority surcharge (multi-tenant, first tenant only) |
| `ongkos_kirim_multitenant` | 2000 | Additional driver fee for multi-tenant orders |
| `biaya_layanan` | 0 | Base service fee per transaction |
| `biaya_ongkos_kirim` | 10 | Driver commission percentage (non-multitenant) |
| `timeout_pesanan` | 10 min | Auto-cancel timeout for unresponded orders |

### Gedung Fields

| Column | Purpose |
|--------|---------|
| `ongkir` | Base delivery fee — paid by **first** tenant |
| `ongkir_multitenant` | Cheaper delivery fee — paid by **second+** tenant |

---

## 3. Order Status Lifecycle

```
               +---> refund_selesai (canceled)
               |
pesanan_masuk -+---> pesanan_diproses (tenant accepts)
                           |
                     siap_diantar / siap_diambil
                           |
                        diantar (driver en route)
                           |
                        selesai (delivered)
                           |
              +---> refund_selesai (refund after completion)
```

**Statuses used in multi-tenant grouping checks:**
- **Active**: `pesanan_masuk`, `pesanan_diproses`, `siap_diambil`, `siap_diantar`, `diantar`
- **Terminal**: `selesai`, `refund_selesai`, `pesanan_ditolak`

---

## 4. Flow 1: Order Creation

**File:** `app/Http/Controllers/Transaksi/TransaksiController.php::store()` (line ~467)

### 4.1 Validation
- Max 2 distinct tenants per order
- All menus must be ready (`isReady = 1`)
- Tenant must be online
- At least 1 driver online for delivery orders

### 4.2 Menu Grouping
Menus are grouped by `tenant_id` via `collect($request->menus)->groupBy(...)`.

### 4.3 Pre-Calculation Per Tenant

For each tenant group:
```php
$isSecondOrMore = count($perTenantCalc) > 0;
$ongkosKirim = $this->getOngkirGedung($ruanganId, $isSecondOrMore);
// First tenant: gedung->ongkir
// Second tenant: gedung->ongkir_multitenant
```

If priority AND first tenant:
```php
$ongkosKirim += ongkos_kirim_prioritas_multitenant (default 4000)
```

Each tenant's pre-calc:
```php
totalFinal = totalHargaMenu + ongkosKirim + biayaLayanan
```

### 4.4 Global Extra Fee (Item-Based)

After all tenants pre-calculated, if total combined items > 10:
```php
$totalExtraGlobal = ($totalSemuaItem - 10) * $biayaExtra  // default 500/item
```

This extra fee is **added only to the FIRST transaction** created (line ~657).

### 4.5 Transaction Creation

Each tenant creates its own `Transaksi` record:
- All share same `multitenant_id`
- `multitenant_id` = `MAX(multitenant_id) + 1` (globally sequential)
- First transaction gets `$totalExtraGlobal` added to `total` and `ongkos_kirim`
- Cashback/voucher applied to **first transaction only**
- For `koin` payment: user's `SaldoKoin` deducted per-transaction

### 4.6 QRIS Payment (Multi-tenant)

Only **one** `Checkout` record is created, tied to the first transaction:
```php
Checkout::create([
    'transaksi_id' => $firstTransaksiId,
    'nominal' => $grandTotal,           // combined total of both tenants
    'midtrans_request_id' => $orderId,
    // ... QRIS response data ...
])
```

### Example Calculation

```
Tenant 1: 7 items × 12,000 = 84,000
Tenant 2: 6 items × 12,000 = 72,000
Base ongkir (gedung->ongkir) = 5,000
Multi ongkir (gedung->ongkir_multitenant) = 2,000
biaya_extra = 500

Total items = 13 > 10 → extra fee = (13-10) × 500 = 1,500

First transaction (whichever tenant is iterated first):
  total = 84,000 + 5,000 + 1,500 + 0 = 90,500
  ongkos_kirim = 6,500

Second transaction:
  total = 72,000 + 2,000 + 0 = 74,000
  ongkos_kirim = 2,000
```

> **Note:** The "first" tenant is determined by PHP array iteration order, not by any business rule. Both tenants share the same `ruangan_id`.

---

## 5. Flow 2: Cancel / Refund

**Entry points (all contain identical logic):**

| Entry | File | Line | Trigger |
|-------|------|------|---------|
| API | `TransaksiController::cancel()` | 1369 | Manual cancel via API |
| Web | `MonitorTransaksiController::postCancel()` | 33 | Manual cancel via admin web |
| Cron | `AutoCancelOrder::handle()` | 21 | Auto-cancel after timeout |

### 5.1 Non-Multitenant Cancel

```
1. Delete CatatVoucher record
2. Restore voucher + cashback quantities
3. Set status → pesanan_ditolak
4. Call $transaksi->refundKoin() → add total to SaldoKoin
5. Record TransaksiSaldoKoin (+)
6. Set status → refund_selesai
7. Notify user via FCM
```

### 5.2 Multitenant Cancel

#### Phase 1: Mark Canceled
```
1. Set transaksi status → refund_selesai
2. Notify user via FCM
```

#### Phase 2: Check Active Siblings
```php
$stillActive = Transaksi::where('multitenant_id', ...)
    ->whereIn('status', ['pesanan_masuk','pesanan_diproses','siap_diambil','siap_diantar','diantar'])
    ->exists();
```

#### Phase 3: First-Cancel Recalculation (only if `isAntar == 1`)

Determine if this is the **first cancel** in the group:
```php
$isFirstCancel = (count of other refund_selesai) === 0;
```

**X Calculation** (adjustment factor):
```php
$totalItems = $activeItems + $cancelItems;

if ($activeItems <= 10) {
    $x = ($totalItems - 10) * 500;          // Full extra fee
} else {
    $x = ($totalItems - 10) * 500           // Total extra fee
       - (($activeItems - 10) * 500);       // Minus active's share
}
$x = max($x, 0);
```

**SWAP Decision:**
```php
$needSwap = ($cancelTx->ongkos_kirim > $activeTx->ongkos_kirim);
```
Swap is needed when the canceled tenant had the higher ongkir (i.e., was the "first" tenant at creation).

**SWAP Branch:**
```
1. Swap ongkos_kirim values between cancel and active
2. If totalItems > 10: reduce active's ongkir by X
3. Recalculate cancel: ongkir = cancelOngkirMulti + extraFeeRefundSalahSatu()
4. Recalculate cancel total: sub_total + cancelOngkirMulti + extraFeeRefundSalahSatu()
5. Recalculate active total: (sub_total + baseOngkir + extraFee(totalItems)) - X
6. Priority case: if totalItems ≤ 10, add multitenantOngkir back to active
```

**NO SWAP Branch:**
```
1. If totalItems > 10: subtract X from whichever has larger ongkir
2. Recalculate cancel: ongkir = cancelOngkirMulti + extraFeeRefundSalahSatu()
3. Recalculate cancel total: sub_total + cancelOngkirMulti + extraFeeRefundSalahSatu()
4. Recalculate active total: (sub_total + baseOngkir + extraFee(totalItems)) - X
```

**NOT First Cancel:**
```
If totalItems ≤ 10 and isPriority: subtract multitenantOngkir from active
```

#### Phase 4: All-Refunded Check
```php
if (!$stillActive) {
    // ALL transactions canceled → full refund
    $totalRefund = sum('total') of all group members
    // Add to user's SaldoKoin
    // Restore voucher/cashback
    // Notify user
}
```

### Example: Case 488 (Tenant 2 canceled, Tenant 1 active)

```
Tenant 1 (first): 7 items, ongkir=6,500, sub_total=84,000
Tenant 2 (second): 6 items, ongkir=2,000, sub_total=72,000

Cancel tenant 2:
  cancelTx = tenant 2 (ongkir=2,000, items=6)
  activeTx = tenant 1 (ongkir=6,500, items=7)
  needSwap = (2,000 > 6,500) = FALSE → NO SWAP
  activeItems = 7 ≤ 10 → X = (13-10)*500 = 1,500
  X subtracted from active (the larger ongkir):
    active.ongkir = max(6,500 - 1,500, 0) = 5,000

  extraFeeRefundSalahSatu(6, 7, 13):
    both ≤ 10, total > 10 → (13-10)*500 = 1,500

  cancel.ongkir = 2,000 + 1,500 = 3,500
  cancel.total = 72,000 + 2,000 + 1,500 = 75,500  ✓ (expected refund)
  active.total = (84,000 + 5,000 + 1,500) - 1,500 = 89,000  ✓
```

### Example: Case 487 (Tenant 1 canceled, Tenant 2 active)

```
Tenant 2 (first): 6 items, ongkir=6,500, sub_total=72,000
Tenant 1 (second): 7 items, ongkir=2,000, sub_total=84,000

Cancel tenant 1:
  cancelTx = tenant 1 (ongkir=2,000, items=7)
  activeTx = tenant 2 (ongkir=6,500, items=6)
  needSwap = (2,000 > 6,500) = FALSE → NO SWAP
  activeItems = 6 ≤ 10 → X = (13-10)*500 = 1,500
  cancel ongkir < active ongkir → subtract X from active:
    active.ongkir = max(6,500 - 1,500, 0) = 5,000

  extraFeeRefundSalahSatu(7, 6, 13):
    both ≤ 10, total > 10 → (13-10)*500 = 1,500

  cancel.ongkir = 2,000 + 1,500 = 3,500
  cancel.total = 84,000 + 2,000 + 1,500 = 87,500  ✓ (expected refund)
  active.total = (72,000 + 5,000 + 1,500) - 1,500 = 77,000  ✓
```

---

## 6. Flow 3: Driver Pickup & Delivery

**File:** `app/Http/Controllers/Masbro/PesananController.php::updateStatus()`

### 6.1 Driver Assignment on Pickup

When a driver accepts (`diantar`) **any** member of a multi-tenant group:
- All group members get the same `driver_id`
- All group members get appropriate status update

Priority orders: the driver who accepts gets assigned to ALL members.
Non-priority orders: driver assignment happens when ALL members reach `siap_diantar`/`diantar`.

### 6.2 Completion (`selesai`)

**Multi-tenant completion logic:**

```
1. If related still pesanan_masuk/diproses/siap_diantar → BLOCKED
   (cannot complete one while other still needs processing)

2. If related status is diantar → AUTO-COMPLETE the related
   (same driver, same bukti_pengantaran, auto-set to selesai)

3. If related status is refund_selesai → proceed normally
   (the other tenant was canceled, this one can complete)
```

### 6.3 Partial Refund on Completion

When completing a multi-tenant order where the other tenant was already refunded (`refund_selesai`):

```php
// Calculate food cost (sub_total + extra fee portion)
$hargaMakananRefundTx = refundTx.sub_total (minus ongkir, extra fee)

// Two scenarios compared:
$totalBayarSemua = all food + base_ongkir + multi_ongkir + priority + extraFee(all items)
$totalBayarSatu  = current food + base_ongkir + priority + extraFee(current items only)

$refundAmount = $totalBayarSemua - $totalBayarSatu
// This is the difference the user overpaid at checkout
// → refunded to user's SaldoKoin
```

This is the **main refund mechanism** for partial multi-tenant cancellations. The cancel flow itself only recalibrates `total`/`ongkos_kirim` fields; the actual coin refund to the user happens here when the surviving tenant completes.

---

## 7. Flow 4: Driver Earnings (Ongkir)

**File:** `PesananController::updateStatus()` (line ~1174)

### 7.1 Multi-Tenant Driver Ongkir

```php
$ongkirMulti = gedung->ongkir_multitenant  // 2000
$baseOngkir  = gedung->ongkir              // 5000
$priorityOngkir = isPriority ? 4000 : 3000
$pajakPersen = 10  // 10% tax

// Determine group status
if (isPriority):
    bothSelesai → $baseOngkir + $priorityOngkir + $ongkirMulti
    oneRefund   → $baseOngkir + $priorityOngkir
    bothRefund  → 0
else:
    bothSelesai → $baseOngkir + $ongkirMulti
    oneRefund   → $baseOngkir
    bothRefund  → 0

// Extra item fee: only from COMPLETED (non-refunded) items
$totalItemsSelesai = sum of items from selesai transactions
if ($totalItemsSelesai > 10):
    $extraOngkir = ($totalItemsSelesai - 10) * 500

$totalOngkir += $extraOngkir
$pajak = 10% * $totalOngkir
$ongkirBersih = $totalOngkir - $pajak  // Driver's net earnings
// Credited to driver's SaldoKoin
```

### 7.2 Non-Multi-Tenant Driver Ongkir

```php
$potongan = (biaya_ongkos_kirim% / 100) * $ongkirAsli
$ongkirBersih = $ongkirAsli - $potongan
```

---

## 8. Flow 5: Cashback on Completion

**File:** `PesananController::updateStatus()` (line ~1086)

Cashback is credited **once per group** when at least one transaction reaches `selesai` and neither is `refund_selesai`:

```php
if (transaction has cashback && !bothRefund && atLeastOneSelesai):
    if (cashback not yet given for this multitenant_id):
        credit cashback_amount to user's SaldoKoin
        mark as given (check by description LIKE "%multitenant #X%")
```

---

## 9. Auto-Cancel (Cron Job)

**File:** `app/Console/Commands/AutoCancelOrder.php::handle()` (line 21)

Runs periodically. For each `pesanan_masuk` transaction with no driver, past timeout threshold:

```
1. Set status → pesanan_ditolak
2. Set catatan_penolakan → auto-cancel message
3. If multitenant_id:
   → Same SWAP/recalculation logic as manual cancel
   → Set status → refund_selesai
   → If all group members done → full refund to user
   → Restore voucher/cashback
```

---

## 10. Helper Methods

### `getOngkirGedung($ruanganId, $isMultitenant)`
**File:** `TransaksiController.php:1270`

```php
return $isMultitenant
    ? gedung->ongkir_multitenant   // Second+ tenant
    : gedung->ongkir;              // First tenant
```

### `extraFee($totalItems)`
**File:** `TransaksiController.php:1851` (also in MonitorTransaksiController, AutoCancelOrder, PesananController)

```php
if ($totalItems > 10):
    return ($totalItems - 10) * 500;
return 0;
```

### `extraFeeRefundSalahSatu($cancelItems, $activeItems, $totalItems)`
**File:** `TransaksiController.php:1864` (also in MonitorTransaksiController:690, AutoCancelOrder:444)

Computes the refundable extra fee when one tenant cancels. **Cost per extra item = 500.**

| Case | Condition | Formula |
|------|-----------|---------|
| 1 | cancel>10, active>10, total>10 | `cancelItems × 500` |
| 2 | cancel≤10, active>10, total>10 | `cancelItems × 500` |
| 3 | cancel≤10, active≤10, total>10 | `(totalItems − 10) × 500` |
| 4 | cancel>10, active≤10, total>10 | `(totalItems − 10) × 500` |

**Mathematical logic:** `max(0, totalItems−10)×500 − max(0, activeItems−10)×500` — the difference between original extra fee and what the surviving tenant should pay.

> **Note:** Case 3 was previously bugged (returning `cancelItems × 500`), fixed 2026-06-19.

---

## 11. Key Files Reference

| File | Purpose |
|------|---------|
| `app/Http/Controllers/Transaksi/TransaksiController.php` | Order creation (store), cancel (cancel), helper methods |
| `app/Http/Controllers/Web/Transaksi/MonitorTransaksiController.php` | Web admin cancel (identical cancel logic) |
| `app/Console/Commands/AutoCancelOrder.php` | Cron auto-cancel (identical cancel logic) |
| `app/Http/Controllers/Masbro/PesananController.php` | Driver flow: pickup, delivery, completion, earnings, refund |
| `app/Models/Transaksi.php` | Order model, relationships, appended attributes |
| `app/Models/Gedung.php` | Building with `ongkir` and `ongkir_multitenant` |
| `app/Models/Pengaturan.php` | Key-value configuration |
| `app/Models/Ruangan.php` | Room → belongsTo Gedung |

---

## 12. Onboarding Checklist for New Developers

- [ ] Understand `Gedung.ongkir` vs `Gedung.ongkir_multitenant` split
- [ ] Understand that "first tenant" = iteration order, not business logic
- [ ] Understand `extraFeeRefundSalahSatu` cases and verify with test data
- [ ] All cancel logic must be kept in sync across 3 files (API, Web, Cron)
- [ ] When modifying cancel: test both SWAP and NO SWAP scenarios
- [ ] Driver earns from `selesai` transactions only (not refunded ones)
- [ ] QRIS multi-tenant: single checkout, shared across both transactions
- [ ] Cashback: given once per group, not per transaction
