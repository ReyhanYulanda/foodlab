---
description: Create an endpoint handling multiple roles (using hasRole / hasAnyRole)
---

# Creating an Endpoint that Handles Multiple Roles

This workflow outlines the standard procedure for creating an API endpoint in the Foodlab project that handles multiple user roles simultaneously. We use the Spatie Laravel Permission package which is integrated into the `User` model (`Spatie\Permission\Traits\HasRoles`).

## 1. Role Verification Strategies

Depending on the business logic, there are two primary ways to handle multiple roles in an endpoint:

### A. Allowing Access to Multiple Roles (Any of Them)
If your endpoint can be accessed by users who have **at least one** of the defined roles (e.g., either 'admin' or 'tenant'), use the `hasAnyRole` method or multiple `hasRole` conditions.

```php
// 1. Permission / Role Check
$user = $request->user();

if (!$user->hasAnyRole(['admin', 'tenant'])) {
    return response()->json([
        'status' => 'failed',
        'message' => 'tidak memiliki akses',
    ], 403);
}

// Continue with logic common to both roles...
```

### B. Executing Different Logic Based on Role
If your endpoint needs to perform different actions depending on the specific role the user holds (e.g., a dashboard endpoint returning different data for 'tenant' vs 'masbro'), use conditional checks with `hasRole()`.

```php
// 2. Branching Logic
$user = $request->user();
$data = [];

if ($user->hasRole('admin')) {
    // Logic specifically for admin
    $data = ExampleModel::all();
} elseif ($user->hasRole('tenant')) {
    // Logic specifically for tenant
    // Note: Assuming User has a relationship to Tenant
    $tenantId = $user->tenant->id;
    $data = ExampleModel::where('tenant_id', $tenantId)->get();
} elseif ($user->hasRole('masbro')) {
    // Logic specifically for masbro (driver)
    $data = ExampleModel::where('driver_id', $user->id)->get();
} else {
    return response()->json([
        'status' => 'failed',
        'message' => 'tidak memiliki akses dengan role ini',
    ], 403);
}
```

## 2. Best Practices for Multiple Roles

When working with `hasRole` and `hasAnyRole` in a single endpoint:

1. **Prioritize Authoritative Roles (Top-down):** If a user can have multiple overlapping roles (e.g. an Admin who is also a Tenant), write your `if/elseif` logic from the most authoritative role (Admin) down to the least, to avoid limiting access unintentionally.
2. **Combine with Permissions (`can()`):** Often, checking for a permission using `$user->can('read example')` is more flexible than strict role checking (`hasRole`), because roles can be assigned specific permissions dynamically via the DB without code changes. Use `hasRole` primarily when the core data architecture strictly diverges based on the role structure (e.g., querying the `Tenant` model vs `DriverDetail` model).

## 3. Example Controller Implementation

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaksi;
use App\Models\User;

class MultiRoleDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Security check: ensure user has at least one of the required roles
        if (!$user->hasAnyRole(['superadmin', 'tenant', 'masbro'])) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        $query = Transaksi::with(['user']);

        // Conditional querying based on roles
        if ($user->hasRole('superadmin')) {
            // Admin sees all
        } elseif ($user->hasRole('tenant')) {
            // Tenant sees only their tenant's transactions
            $query->where('tenant_id', $user->id); 
        } elseif ($user->hasRole('masbro')) {
            // Masbro (driver) sees only transactions assigned to them
            $query->where('driver_id', $user->id);
        }

        $data = $query->orderByDesc('created_at')->paginate(10);

        return response()->json([
            'status' => 'success',
            'message' => 'data dashboard berhasil didapatkan',
            'data' => $data
        ]);
    }
}
```
