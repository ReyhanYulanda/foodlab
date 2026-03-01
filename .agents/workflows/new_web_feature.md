---
description: Create a new feature in the web panel (Menu, Backend, Roles, Testing)
---

# Creating a New Feature on the Web Panel

This workflow explains the end-to-end process of adding a new feature module into the admin/web panel, from defining the interface menu to restricting access via roles and permissions.

## 1. Add the Menu on the Web UI

Before touching any code, it is recommended to define the interface entry point in the database via the admin panel. 

- Navigate to the **MENU** management section in the web application.
- Click **Tambah** (Add).
- Fill in the required form:
  - **Judul**: The display name of your menu (e.g., "Manajemen Produk").
  - **URL**: The base route for your feature (e.g., `produk`).
  - **Ikon**: Icon class string (e.g., `fas fa-box`).
  - **Permission ID / Role**: Match this so the system knows what permission is required to view this menu. If the permission structure expects specific nomenclature, typically it takes a format like `read produk`.

## 2. Generate Backend Components (Migrations, Models, Controllers, Routes)

Next, build the foundation of your feature according to typical Laravel MVC pattern:

### A. Migrations & Model (if the feature was building new table)
Run standard `php artisan make:model NamaModel -m` to create the model and migration together. Set up the schema, making sure to include `$table->softDeletes()` and timestamps if needed.

```php
// Database/Migrations
Schema::create('produk', function (Blueprint $table) {
    $table->id();
    $table->string('nama_produk');
    $table->integer('harga');
    $table->softDeletes();
    $table->timestamps();
});

// Models/Produk.php
class Produk extends Model {
    use HasFactory, SoftDeletes;
    protected $table = 'produk';
    protected $fillable = ['nama_produk', 'harga'];
}
```

### B. Controller
Create a controller (\app\Http\Controllers\Web) to manage the views and operations for this feature. 
Ensure the views being returned map to the corresponding Blade templates (`resources/views/pages/produk/index.blade.php`).

```php
// Http/Controllers/ProdukController.php
namespace App\Http\Controllers;

use App\Models\Produk;
use Illuminate\Http\Request;

class ProdukController extends Controller
{
    public function index()
    {
        $data = Produk::latest()->paginate(10);
        return view('pages.produk.index', compact('data'));
    }
    
    // store, update, destroy...
}
```

### C. Web Routing
Register the routes in `routes/web.php`. Make sure your routes are protected behind standard authentication.
If you use resourceful routing, do so inside the appropriate middleware group.

Example :
```php
// routes/web.php
Route::middleware(['shared', 'auth', 'role:tenant|kdh|admin'])->group(function () {
    // Other routes...

    Route::resource('tenant', TenantController::class);

    Route::post('/notifikasi/kirim', [NotifikasiController::class, 'kirim'])->name('notifikasi.kirim');
    // Edit & delete permissions...
});
```
*(Optionally apply middleware cleanly on the Controller constructor, or directly on the route definitions).*

## 3. Set Up Roles and Permissions

Now the system needs to recognize the permissions defined in the routes or views (`read produk`, `create produk`, etc.).

- In the web panel's **MENU**, navigate to the **ROLE** (or Permissions) management page.
- Select the role you want to grant access to (e.g., *Admin*, *Tenant*).
- From the UI, check or add the newly created permissions (`read produk`, `create produk`, `update produk`, `delete produk`).
  - Note: *If these permissions do not appear on the UI by default, you may need to insert them directly into the `permissions` table or sync them from an initialization command.*

## 4. Testing

To verify the setup:
1. Log in with a user mapped to the target Role.
2. Check the sidebar: the new **MENU** item should be visible.
3. Click the menu to hit the route. It should render the designated view.
4. Test the CRUD operations: create a record, edit, and delete. 
5. Verify the permission walls by logging in as a user *without* the role. That user should not see the menu and should receive a 403 error when manually visiting the route URL.