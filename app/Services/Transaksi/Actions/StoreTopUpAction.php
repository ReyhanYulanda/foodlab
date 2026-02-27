<?php

namespace App\Services\Transaksi\Actions;

use App\Helpers\TransaksiHelper;
use App\Jobs\CekTopupStatusJob;
use App\Models\TopUp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StoreTopUpAction
{
    public function execute(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'nominal' => 'required|integer|min:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $requestId = TransaksiHelper::generateRequestId();
        $timeout = TransaksiHelper::generateTimeout();

        $dataToSend = [
            'request_id_' => $requestId,
            'nama_' => $user->name,
            'nominal_topup_' => $request->nominal,
            'tanggal_akhir_tagihan_' => $timeout->format('d-m-Y H:i:s'),
        ];

        $apiKey = config('custom.ubisma_api_key');
        $apiUrl = config('custom.ubisma_api_url');

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'Accept' => 'application/json',
        ])->asJson()->post($apiUrl, [
                    'data' => [$dataToSend]
                ]);

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal terhubung ke server UBISMA.',
                'debug' => $response->body(),
            ], $response->status());
        }

        $ubismaData = $response->json('data');

        Log::info('Response dari UBISMA:', $response->json());

        if (!$ubismaData || !isset($ubismaData['kode_bayar_mandiri_'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Response UBISMA tidak valid atau tidak berisi kode bayar.',
                'debug' => $response->json()
            ], 500);
        }

        $topup = TopUp::create([
            'user_id' => $user->id,
            'request_id' => $requestId,
            'nominal' => $request->nominal,
            'kode_bayar' => $ubismaData['kode_bayar_mandiri_'] ?? null,
            'tgl_akhir_tagihan' => $timeout,
        ]);

        CekTopupStatusJob::dispatch($topup);

        return response()->json([
            'status' => 'success',
            'data' => [
                'topup' => $topup,
                'ubisma_response' => $ubismaData
            ]
        ]);
    }
}
