---
description: Workflow untuk refactoring kode Laravel agar konsisten dengan arsitektur Repository-Service-Action pattern
---

# Refactoring Workflow — Foodlab Project

> **Tujuan**: Memindahkan logic dari Controller ke layer yang sesuai (Repository, Service/Action, DTO, Helper) **tanpa mengubah struktur folder** yang sudah ada.

---

## 🚨 Aturan Utama

1. **JANGAN mengubah arsitektur folder.** Folder structure yang sudah ada bersifat final.
2. **JANGAN membuat folder baru** di luar pola yang sudah ada.
3. **JANGAN mengubah behaviour / response** yang sudah ada. Refactoring hanya memindahkan kode, bukan mengubah fungsionalitas.
4. **JANGAN menghapus route, endpoint, atau nama method** di Controller.
5. **JANGAN membuat method baru di Repository** jika sudah ada method dengan fungsi yang sama. **Selalu cek method yang sudah ada** di Repository terkait sebelum menambahkan method baru. Gunakan yang sudah ada.

---

## 📂 Arsitektur Folder (Referensi)

```
app/
├── DTO/                          # Data Transfer Objects
│   ├── MenuDTO.php
│   └── HistoryFilterDTO.php
├── Http/
│   └── Controllers/
│       └── {Domain}/             # Controller per domain (Kelola, Tenant, Api, Web, dll)
│           └── XxxController.php
├── Repositories/                 # Query & data-access layer (flat, tanpa subfolder)
│   ├── MenuRepository.php
│   ├── TenantRepository.php
│   ├── TransaksiRepository.php
│   └── ...
├── Response/
│   └── ResponseApi.php           # Standard JSON response wrapper
└── Services/
    └── {Domain}/                 # Sesuai domain Controller
        ├── Actions/              # Satu class = satu use-case
        │   ├── CreateMenuAction.php
        │   ├── UpdateMenuAction.php
        │   └── ...
        ├── Helpers/              # Utility class reusable di domain ini
        │   └── ImageUploader.php
        └── XxxService.php        # (legacy) Service class monolitik (boleh ada, tapi prefer Action)
```

### Penjelasan Tiap Layer

| Layer | Tanggung Jawab | Namespace |
|---|---|---|
| **Controller** | Menerima request, authorize, **mendelegasikan** ke Action, mengembalikan response | `App\Http\Controllers\{Domain}` |
| **Action** | Satu class = satu use-case bisnis (validate, orchestrate Repository, return result) | `App\Services\{Domain}\Actions` |
| **Repository** | Semua query database (Eloquent). **TIDAK** boleh ada query di Controller/Action secara langsung jika sudah memiliki Repository | `App\Repositories` |
| **DTO** | Memformat data antar layer, menghindari passing array mentah | `App\DTO` |
| **Helper** | Utility stateless yang bisa dipanggil di mana saja (upload file, format string, dll) | `App\Services\{Domain}\Helpers` |
| **ResponseApi** | Standard JSON response wrapper (`success`, `error`, `forbidden`, `serverError`) | `App\Response` |

---

## ✅ Contoh Refactored: `Kelola/TenantController`

Berikut contoh Controller yang **sudah menerapkan refactoring dengan benar**:

```php
// app/Http/Controllers/Kelola/TenantController.php

class TenantController extends Controller
{
    protected ResponseApi $response;

    public function __construct(ResponseApi $response)
    {
        $this->response = $response;
    }

    // ✅ Controller HANYA: authorize → delegate → respond
    public function storeMenu(Request $request, CreateMenuAction $action)
    {
        $this->authorize('create katalog');
        $menu = $action->execute($request);
        return $this->response->success(['menu' => $menu], 'menu berhasil ditambahkan');
    }
}
```

```php
// app/Services/Tenant/Actions/CreateMenuAction.php

class CreateMenuAction
{
    protected $menuRepo;
    protected $tenantRepo;

    // ✅ Inject Repository via constructor
    public function __construct(MenuRepository $menuRepo, TenantRepository $tenantRepo)
    {
        $this->menuRepo = $menuRepo;
        $this->tenantRepo = $tenantRepo;
    }

    // ✅ Satu method `execute()` yang menjalankan satu use-case
    public function execute($request)
    {
        // 1. Validate
        $validator = Validator::make($request->all(), [...]);
        $validator->validate();

        // 2. Resolve data via Repository
        $tenant = $this->tenantRepo->getByUser($request->user()->id);

        // 3. Business logic (upload, transform, dll)
        $gambarUrl = null;
        if ($request->hasFile('gambar')) {
            $gambarUrl = ImageUploader::upload($request->file('gambar'));
        }

        // 4. DTO untuk format data
        $dto = new MenuDTO([...]);

        // 5. Persist via Repository
        return $this->menuRepo->create($dto->toArray());
    }
}
```

```php
// app/Repositories/MenuRepository.php

class MenuRepository
{
    // ✅ Hanya berisi operasi database
    public function create(array $data) { return Menus::create($data); }
    public function find($id) { return Menus::find($id); }
    public function update($id, array $data) { ... }
    public function delete($id) { ... }
}
```

---

## 📋 Step-by-Step Refactoring

Ketika diminta refactoring sebuah Controller, ikuti langkah-langkah berikut **secara berurutan**:

### Step 1: Analisis Controller yang Akan Di-refactor

1. Buka file Controller target
2. Identifikasi setiap method dan catat:
   - Apakah ada **query Eloquent langsung** (harus dipindah ke Repository)
   - Apakah ada **business logic** seperti validasi, transformasi data, kalkulasi (harus dipindah ke Action)
   - Apakah ada **utility reusable** seperti upload gambar, generate kode (harus dipindah ke Helper)
   - Apakah ada **data array mentah** yang di-pass antar layer (pertimbangkan DTO)
3. Tentukan **domain** Controller tersebut (lihat namespace-nya, contoh: `Kelola`, `Tenant`, `Api`)

### Step 2: Buat / Update Repository

1. Cek apakah Repository untuk Model terkait **sudah ada** di `app/Repositories/`
2. Jika **sudah ada**:
   - **Baca semua method** yang sudah ada di Repository tersebut
   - Cek apakah query yang dibutuhkan **sudah tersedia** dalam method yang ada
   - **Jika sudah ada method dengan fungsi yang sama → GUNAKAN method tersebut, JANGAN buat baru**
   - Hanya tambahkan method baru jika benar-benar belum ada method yang bisa memenuhi kebutuhan
3. Jika **belum ada**: buat file baru di `app/Repositories/{Model}Repository.php`
4. Pindahkan semua query Eloquent dari Controller ke Repository

**Aturan Repository:**
- Namespace: `App\Repositories`
- **TIDAK** ada subfolder — semua Repository flat di `app/Repositories/`
- Satu Repository per Model (atau per aggregate root)
- Method hanya berisi operasi database (query, create, update, delete)
- **TIDAK** ada business logic, validasi, atau HTTP-related code

```php
// Template Repository
namespace App\Repositories;

use App\Models\{ModelName};

class {ModelName}Repository
{
    public function find($id) { return {ModelName}::find($id); }
    public function create(array $data) { return {ModelName}::create($data); }
    public function update($id, array $data) { ... }
    public function delete($id) { ... }
    // Tambahkan method sesuai kebutuhan query
}
```

### Step 3: Buat Action Class

1. Buat folder `app/Services/{Domain}/Actions/` jika belum ada
2. Buat satu Action class per use-case/method Controller
3. Naming convention: `{Verb}{Noun}Action.php` (contoh: `CreateMenuAction.php`, `UpdateMenuAction.php`)

**Aturan Action:**
- Namespace: `App\Services\{Domain}\Actions`
- Inject Repository via **constructor** (dependency injection)
- Punya satu public method: `execute(...)`
- Berisi: validasi, resolve data, business logic, persist via Repository
- **TIDAK** ada `return response()->json(...)` — kembalikan data mentah saja

```php
// Template Action
namespace App\Services\{Domain}\Actions;

use App\Repositories\{Model}Repository;

class {Verb}{Noun}Action
{
    protected ${model}Repo;

    public function __construct({Model}Repository ${model}Repo)
    {
        $this->{model}Repo = ${model}Repo;
    }

    public function execute($request)
    {
        // 1. Validate
        // 2. Resolve data via Repository
        // 3. Business logic
        // 4. (Optional) DTO
        // 5. Persist via Repository
        // 6. Return data (bukan response)
    }
}
```

### Step 4: (Opsional) Buat DTO

Buat DTO jika ada data array yang sering di-pass antar layer atau perlu format yang konsisten.

- Lokasi: `app/DTO/{Name}DTO.php`
- Namespace: `App\DTO`

```php
// Template DTO
namespace App\DTO;

class {Name}DTO
{
    protected $data = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function toArray()
    {
        return $this->data;
    }
}
```

### Step 5: (Opsional) Buat Helper

Buat Helper jika ada utility yang reusable di domain ini (upload file, generate kode, format string).

- Lokasi: `app/Services/{Domain}/Helpers/{Name}.php`
- Namespace: `App\Services\{Domain}\Helpers`
- Gunakan **static method** agar mudah dipanggil

```php
// Template Helper
namespace App\Services\{Domain}\Helpers;

class {Name}
{
    public static function doSomething($param)
    {
        // utility logic
    }
}
```

### Step 6: Refactor Controller

1. Inject `ResponseApi` di constructor (jika Controller mengembalikan API response)
2. Inject Action class di **method parameter** (Laravel auto-resolve via DI)
3. **Hapus** semua logic dari Controller method
4. Controller method hanya berisi 3 hal:
   - `$this->authorize(...)` atau permission check
   - `$action->execute(...)` untuk delegasi
   - `return $this->response->success(...)` untuk response

```php
// Template Refactored Controller Method
public function someMethod(Request $request, SomeAction $action)
{
    $this->authorize('permission-name');

    $result = $action->execute($request);

    return $this->response->success(['key' => $result], 'pesan sukses');
}
```

### Step 7: Verifikasi

1. Pastikan **tidak ada query Eloquent** langsung di Controller
2. Pastikan **tidak ada business logic** di Controller
3. Pastikan semua **import/use statement** sudah benar
4. Pastikan **namespace** sesuai lokasi file
5. Pastikan **response format** tidak berubah dari sebelumnya
6. Test endpoint menggunakan API client (Postman/Insomnia) atau unit test jika ada

---

## ❌ Anti-Pattern (JANGAN Lakukan)

| ❌ Salah | ✅ Benar |
|---|---|
| Query Eloquent di Controller | Pindahkan ke Repository |
| Validasi di Controller | Pindahkan ke Action |
| Upload file langsung di Controller | Gunakan Helper class |
| Membuat subfolder di `Repositories/` | Tetap flat di `app/Repositories/` |
| Membuat folder `Services/` baru yang tidak sesuai domain Controller | Gunakan domain yang sama dengan namespace Controller |
| Mengubah nama method Controller | Pertahankan nama method yang sudah ada |
| Mengubah response format/structure | Pertahankan format response yang sudah ada |
| Satu Action class untuk banyak use-case | Satu Action = Satu use-case (Single Responsibility) |
| Return `response()->json()` di Action | Return data mentah, biarkan Controller yang format response |
| Membuat method Repository baru padahal sudah ada yang fungsinya sama | Gunakan method Repository yang sudah ada, jangan duplikasi |

---

## 🔍 Checklist Sebelum Commit

- [ ] Controller hanya berisi: authorize → delegate → respond
- [ ] Semua query database ada di `app/Repositories/` (flat, tanpa subfolder)
- [ ] Setiap use-case punya Action class di `app/Services/{Domain}/Actions/`
- [ ] Namespace dan lokasi file sudah sesuai
- [ ] Tidak ada folder baru yang dibuat di luar pola arsitektur
- [ ] Response format sama persis dengan sebelum refactoring
- [ ] Semua import/use statement sudah benar
- [ ] Tidak ada dead code atau unused imports