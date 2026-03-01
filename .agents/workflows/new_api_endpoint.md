---
description: Create a new API endpoint (Migration, Model, Controller)
---

# Creating a New API Endpoint

This workflow outlines the standard procedure for creating a new API endpoint in the Foodlab project, covering the Migration, Model, and Controller generation, while adhering to the established architecture, permission handling, database transaction management, and notifications.

## 1. Migration (if the feature was building new table)
When creating a new table, make sure to include standard Laravel timestamps and soft deletes if it's a record that needs to be retained.

**Example Migration:**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExampleTable extends Migration
{
    public function up()
    {
        Schema::create('example_table', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            // Add your custom columns here...
            
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('example_table');
    }
}
```

## 2. Model
Models should use the `HasFactory` and `SoftDeletes` traits. Ensure `protected $table` and `protected $fillable` are defined.

**Example Model:**
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExampleModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'example_table';
    
    protected $fillable = [
        'user_id',
        // Other fillable attributes...
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
```

## 3. Controller
When returning data, the standard format standardizes responses (`status`, `message`, `data`). It should include proper authorization using Laravel's `$user->can()` mechanism, database transaction management (`DB::beginTransaction()`, `DB::commit()`, `DB::rollBack()`) to prevent race conditions, and notification handling via the `Firebases` service if required.

**Example Controller Implementation:**
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\ExampleModel;
use App\Models\User;
use App\Services\Firebases;
use Throwable;

class ExampleController extends Controller
{
    // Retrieve collection of data
    public function index(Request $request)
    {
        $user = $request->user();

        // 1. Permission Check
        if (!$user->can('read example')) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $data = ExampleModel::with('user')
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => 'success',
            'message' => 'data berhasil didapatkan',
            'data' => $data
        ]);
    }

    // Store new data
    public function store(Request $request, Firebases $firebases)
    {
        $user = $request->user();
        
        // 1. Permission Check
        if (!$user->can('create example')) { // Or whatever the valid permission should be
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            // Validation rules...
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => $validator->errors()->all()
            ], 400);
        }

        // 2. Prevent Race Condition / Transaction Error
        DB::beginTransaction();
        try {
            $example = ExampleModel::create([
                'user_id' => $user->id,
                // other attributes
            ]);

            // 3. Notification Handling using Firebases Service
            // e.g. Sending notifications to other users having fcm tokens
            $fcmTokens = User::whereNotNull('fcm_token')
                ->where('id', '!=', $user->id) 
                ->with('fcmTokens')
                ->get()
                ->flatMap(fn($u) => $u->fcmTokens->pluck('fcm_token'))
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            if (!empty($fcmTokens)) {
                $firebases
                    ->withNotification('New Example Created', 'A new example has been created.')
                    ->withData([
                        'title' => 'New Example Created',
                        'body' => 'A new example has been created.',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ])
                    // Use ->sendToFallback(), ->sendToTenant(), or similar depending on context
                    ->sendToFallback($fcmTokens); 
            }

            // Commit on success
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Data berhasil disimpan',
                'data' => $example
            ]);
        } catch (Throwable $th) {
            // Rollback on failure
            DB::rollBack();
            Log::error($th->getMessage());
            
            return response()->json([
                'status' => 'server error',
                'message' => 'terjadi kesalahan di server'
            ], 500);
        }
    }
}
```