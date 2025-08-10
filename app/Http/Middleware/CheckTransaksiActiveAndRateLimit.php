<?php

namespace App\Http\Middleware;

use App\Models\Transaksi;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CheckTransaksiActiveAndRateLimit
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
        $transaksiId = $request->route('transaksiId');
        $transaksi = Transaksi::findOrFail($transaksiId);

        // ✅ Cek user terkait transaksi
        if (!in_array(auth()->id(), [$transaksi->user_id, $transaksi->tenant_id, $transaksi->driver_id])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak berhak mengakses transaksi ini.'
            ], 403);
        }

        // ✅ Cek status transaksi
        if ($transaksi->trashed() || $transaksi->status === 'selesai' || $transaksi->status === 'refund_selesai') {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaksi sudah selesai, chat tidak tersedia.'
            ], 400);
        }

        if ($transaksi->id === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaksi tidak ditemukan.'
            ], 404);
        }

        // ✅ Rate limit: 1 pesan per 3 detik per user per transaksi
        $cacheKey = "chat_rate_limit_user_" . auth()->id() . "_trx_" . $transaksiId;
        if (Cache::has($cacheKey)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terlalu cepat mengirim pesan, coba lagi sebentar.'
            ], 429);
        }

        // Set rate limit 3 detik
        Cache::put($cacheKey, true, now()->addSeconds(1));
        return $next($request);
    }
}
