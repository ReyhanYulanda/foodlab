<?php

namespace App\Helpers;

use App\Models\Ruangan;
use App\Models\TopUp;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TransaksiHelper
{
    /**
     * Get Ongkir Gedung
     */
    public static function getOngkirGedung($ruanganId, $isMultitenant = false)
    {
        $ruangan = Ruangan::with('gedung')->find($ruanganId);
        if (!$ruangan || !$ruangan->gedung) {
            return 0;
        }

        return $isMultitenant
            ? ($ruangan->gedung->ongkir_multitenant ?? 0)
            : ($ruangan->gedung->ongkir ?? 0);
    }

    public static function generateMidtransRequestId()
    {
        $starting = config('custom.midtrans_request_id_start');
        Log::info("Starting MIDTRANS_REQUEST_ID_START: " . $starting);

        if (is_null($starting)) {
            throw new \Exception("MIDTRANS_REQUEST_ID_START belum diset di environment");
        }

        $lastNumber = TopUp::whereNotNull('midtrans_request_id')
            ->where('midtrans_request_id', 'like', 'foodlab-%')
            ->selectRaw("MAX(CAST(SUBSTRING_INDEX(midtrans_request_id, '-', -1) AS UNSIGNED)) as max_id")
            ->value('max_id');

        $next = ($lastNumber && $lastNumber >= $starting) ? $lastNumber + 1 : $starting;

        return 'foodlab-' . $next;
    }

    // Start dari 102 dan terus naik
    public static function generateRequestId()
    {
        $starting = config('custom.request_id_start');

        if (is_null($starting)) {
            throw new \Exception("REQUEST_ID_START belum diset di environment");
        }

        $last = TopUp::max('request_id');

        return ($last && $last >= $starting) ? $last + 1 : $starting;
    }

    public static function generateTimeout()
    {
        return Carbon::now()->addHour();
    }
}
