<?php

namespace App\Services\Transaksi\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class PushToUbismaAction
{
    public function execute(Request $request)
    {
        $data = $request->input('data.0');

        $validator = Validator::make($data, [
            'request_id_' => 'required|integer|digits_between:1,10',
            'nama_' => 'required|string|max:100',
            'nominal_topup_' => 'required|integer|digits_between:1,10',
            'tanggal_akhir_tagihan_' => 'required|date_format:d-m-Y H:i:s',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'code' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = [
            'procedure' => 'pfoodlab_topup',
            'data' => [$data],
        ];

        $apiKey = config('custom.mis_api_key');
        $apiUrl = config('custom.mis_api_url');

        if (is_null($apiUrl) || empty($apiUrl)) {
            return response()->json([
                'status' => 'error',
                'message' => 'MIS API URL belum diset di konfigurasi.',
            ], 500);
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'Accept' => 'application/json',
        ])->asJson()->post($apiUrl, $payload);

        return response()->json([
            'status' => $response->json('status'),
            'code' => $response->json('code'),
            'data' => $response->json('data'),
            'debug' => [
                'headers' => $response->headers(),
                'payload_sent' => $payload,
                'raw_response' => $response->json(),
                'http_status' => $response->status(),
            ]
        ], $response->status());
    }
}
