<?php

namespace App\Services\Transaksi\Actions;

use App\Models\SaldoKoin;
use App\Models\TopUp;
use App\Models\TransaksiSaldoKoin;
use App\Services\Firebases;
use Illuminate\Support\Facades\Log;

class CheckMidtransTopUpAction
{
    public function execute($midtransRequestId)
    {
        $topup = TopUp::where('midtrans_request_id', $midtransRequestId)->first();

        if (!$topup) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data TopUp tidak ditemukan.'
            ], 404);
        }

        $serverKey = config('custom.midtrans_server_key');
        Log::info("MIDTRANS SERVER KEY : " . $serverKey);
        $authHeader = 'Basic ' . base64_encode($serverKey . ':');

        $apiUrl = config('custom.midtrans_get_api_url') . $midtransRequestId . '/status';
        Log::info('API URL : ' . $apiUrl);

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $authHeader,
            'Accept: application/json',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $curlError = curl_error($ch);
            curl_close($ch);
            Log::error('cURL error saat cek status TopUp Midtrans: ' . $curlError);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghubungi Midtrans.',
                'debug' => $curlError
            ], 500);
        }

        curl_close($ch);

        $midtransData = json_decode($response, true);

        if ($httpCode >= 400 && $httpCode !== 404) {
            Log::error('Gagal cek status ke Midtrans', [
                'request_id' => $midtransRequestId,
                'http_code' => $httpCode,
                'midtrans_response_body' => $midtransData,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mendapatkan status dari Midtrans.',
                'debug' => $midtransData,
            ], $httpCode);
        }

        if ($httpCode === 404 || !isset($midtransData['transaction_status'])) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'topup' => $topup,
                    'status_midtrans' => 'Not Found / Pengguna Belum Membayar'
                ]
            ]);
        }

        $transactionStatus = $midtransData['transaction_status'];

        if (in_array($transactionStatus, ['settlement', 'capture'])) {
            if ($topup->status !== 'terbayar') {
                $topup->update([
                    'status' => 'terbayar'
                ]);

                $saldoKoin = SaldoKoin::firstOrCreate(
                    ['user_id' => $topup->user_id],
                    ['jumlah' => 0]
                );
                $saldoSebelum = $saldoKoin->jumlah;
                $saldoKoin->increment('jumlah', $topup->nominal);

                TransaksiSaldoKoin::create([
                    'user_id' => $topup->user_id,
                    'jumlah' => $topup->nominal,
                    'tipe' => 'masuk',
                    'deskripsi' => "Top Up via QRIS (Midtrans Request ID: {$midtransRequestId})"
                ]);

                $firebases = new Firebases();
                $firebases->withNotification('Topup Koin', "Topup koin sebessar {$topup->nominal} via QRIS berhasil 🎉. \nSaldo sebelumnya : {$saldoSebelum} \nSaldo saat ini : {$saldoKoin->fresh()->jumlah}")->sendMessages($topup->user->fcm_token);

                Log::info("TopUp via Midtrans berhasil: ID {$topup->id}, Nominal ditambahkan: {$topup->nominal}");
            }
        } elseif (in_array($transactionStatus, ['cancel', 'expire', 'deny'])) {
            if ($topup->status !== 'failed') {
                $topup->update([
                    'status' => 'failed'
                ]);

                $firebases = new Firebases();
                $firebases->withNotification('Topup Koin', "Topup koin sebessar {$topup->nominal} via QRIS dibatalkan/kadaluarsa.")->sendMessages($topup->user->fcm_token);

                Log::info("TopUp via Midtrans gagal/expire: ID {$topup->id}, Status: {$transactionStatus}");
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'topup' => $topup,
                'midtrans_response' => $midtransData
            ]
        ]);
    }
}
