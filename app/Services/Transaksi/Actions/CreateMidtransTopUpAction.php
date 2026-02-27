<?php

namespace App\Services\Transaksi\Actions;

use App\Helpers\TransaksiHelper;
use App\Jobs\CekMidtransTopupStatusJob;
use App\Models\TopUp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CreateMidtransTopUpAction
{
    public function execute(Request $request, array $biaya)
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

        $midtransRequestId = TransaksiHelper::generateMidtransRequestId();
        $dataToSend = [
            'payment_type' => 'qris',
            'transaction_details' => [
                'order_id' => $midtransRequestId,
                'gross_amount' => $biaya['total_bayar_user'],
            ],
        ];

        $apiUrl = config('custom.midtrans_post_api_url');
        $serverKey = config('custom.midtrans_server_key');
        $authHeader = 'Basic ' . base64_encode($serverKey . ':');

        $jsonPayload = json_encode($dataToSend);

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $authHeader,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $curlError = curl_error($ch);
            curl_close($ch);
            Log::error('cURL error saat request ke Midtrans: ' . $curlError);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghubungi Midtrans.',
                'debug' => $curlError
            ], 500);
        }

        curl_close($ch);

        $midtransData = json_decode($response, true);

        if ($httpCode >= 400) {
            Log::error('Gagal request ke Midtrans', [
                'request_payload' => $dataToSend,
                'http_code' => $httpCode,
                'midtrans_response_body' => $midtransData,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal terhubung ke Midtrans.',
                'debug' => $midtransData,
            ], $httpCode);
        }

        Log::info('Response dari Midtrans:', $midtransData);

        $actions = $midtransData['actions'] ?? null;
        $transactionStatus = $midtransData['transaction_status'] ?? null;

        if (!is_array($actions) || empty($actions)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data actions tidak tersedia dalam response Midtrans.',
                'debug' => $midtransData
            ], 500);
        }

        $generateQrAction = collect($actions)->firstWhere('name', 'generate-qr-code');

        if (!$generateQrAction || !isset($generateQrAction['url'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'URL QR Code tidak ditemukan dalam response Midtrans actions.',
                'debug' => $midtransData
            ], 500);
        }

        $qrCodeUrl = $generateQrAction['url'];

        $topup = TopUp::create([
            'user_id' => $user->id,
            'midtrans_request_id' => $midtransRequestId,
            'nominal' => $biaya['nominal_topup'],
            'biaya_midtrans' => $biaya['biaya_midtrans'],
            'biaya_ubisma' => $biaya['biaya_ubsima'],
            'total_biaya_admin' => $biaya['total_biaya_admin'],
            'total_bayar_user' => $biaya['total_bayar_user'],
            'kode_bayar' => $qrCodeUrl,
            'status' => $transactionStatus === 'pending' ? 'pending' : 'failed',
            'tgl_akhir_tagihan' => TransaksiHelper::generateTimeout(),
        ]);

        CekMidtransTopupStatusJob::dispatch($topup);

        return response()->json([
            'status' => 'success',
            'data' => [
                'topup' => $topup,
                'midtrans_response' => [
                    'order_id' => $midtransRequestId,
                    'qr_url' => $qrCodeUrl,
                    'status' => $transactionStatus,
                    'biaya' => $biaya
                ]
            ]
        ]);
    }
}
