<?php

namespace App\Http\Middleware;

use App\Models\Transaksi;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RequestLogger
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $tenantId = null;
        $pembeliId = null;

        // Coba ambil tenant_id dari user login
        if ($user && $user->hasRole('tenant')) {
            $tenantId = optional($user->tenant)->id;
        }

        // Coba deteksi transaksi dari route atau input
        $transaksiId = $request->route('id') ?? $request->route('transaksiId') ?? $request->input('transaksi_id');

        if ($transaksiId) {
            $transaksi = Transaksi::with('listTransaksiDetail.menus')->find($transaksiId);
            if ($transaksi) {
                $pembeliId = $transaksi->user_id;

                // Kalau belum ada tenant_id dari login, ambil dari transaksi
                if (!$tenantId) {
                    $firstTenantId = optional($transaksi->listTransaksiDetail->first()?->menus)->tenant_id;
                    $tenantId = $firstTenantId;
                }
            }
        }

        // Log request
        Log::info('API Request', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'endpoint' => $request->path(),
            'url' => $request->fullUrl(),
            'input' => $request->except(['password', 'password_confirmation']),
            'user_id' => optional($user)->id,
            'tenant_id' => $tenantId,
            'pembeli_id' => $pembeliId,
        ]);

        $response = $next($request);

        // Log response
        Log::info('API Response', [
            'status' => $response->status(),
        ]);

        return $response;
    }
}
