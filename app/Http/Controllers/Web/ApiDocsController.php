<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ApiDocsController extends Controller
{
    public function show(Request $request, $file = 'index.html')
    {
        $user = $request->user();

        // Pengecekan Permission
        if (!$user || !$user->can('read documentation')) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        // Hindari directory traversal attack
        $file = str_replace('..', '', $file);
        $path = storage_path('app/api-docs/' . $file);

        if (!File::exists($path)) {
            if ($request->wantsJson() || \Illuminate\Support\Str::endsWith($file, '.json')) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'File not found',
                ], 404);
            }
            abort(404, 'File not found');
        }

        // Return the protected file
        return response()->file($path);
    }
}
