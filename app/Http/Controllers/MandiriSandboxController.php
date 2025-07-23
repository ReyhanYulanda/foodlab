<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;

class MandiriSandboxController extends Controller
{
    public function createVA()
    {
        // === STEP 1: Setup ===
        $privateKeyPath = storage_path('app/mandiri/API_Portal.pem');
        $privateKeyPassword = 'mandiri123';
        $clientId = '4351d2b8-8a0c-49b7-8f34-fdc42a4d1ae3';
        $clientSecret = '325fe2bc-7d9b-4e15-a056-c6d6824a9f1f';
        $accessToken = 'isi_access_token_sandbox_kamu'; // ini bisa hardcoded dulu
        $partnerId = 'SANDBOX';
        $endpoint = '/openapi/transaction/v1.0/transfer-va/create-va';
        $method = 'POST';

        // === STEP 2: Data Payload ===
        $payload = [
            "partnerServiceId"     => "89661",
            "customerNo"           => "8966112900000391",
            "virtualAccountNo"     => "8966112900000391",
            "virtualAccountName"   => "Jokul Doe",
            "virtualAccountEmail"  => "jokul@email.com",
            "virtualAccountPhone"  => "6281828384858",
            "trxId"                => "abcd12346",
            "totalAmount" => [
                "value"     => "75000.00",
                "currency"  => "IDR"
            ],
            "billDetails" => [
                [
                    "billAmount" => [
                        "value"     => "75000.00",
                        "currency"  => "IDR"
                    ]
                ]
            ],
            "expiredDate" => "2022-12-14T23:59:59+07:00"
        ];

        $jsonMinify = json_encode($payload, JSON_UNESCAPED_SLASHES);

        // === STEP 3: Generate SHA256 -> HEX -> Lower ===
        $sha256Bin = hash('sha256', $jsonMinify, true);
        $hex = bin2hex($sha256Bin);
        $lowerHex = strtolower($hex);

        // === STEP 4: Timestamp ===
        $timestamp = now('Asia/Jakarta')->format('c'); // ISO 8601

        // === STEP 5: Generate Signature (HMAC SHA512) ===
        $stringToSign = $method . ':' . $endpoint . ':' . $accessToken . ':' . $lowerHex . ':' . $timestamp;
        $signature = base64_encode(hash_hmac('sha512', $stringToSign, $clientSecret, true));

        // === STEP 6: Kirim ke Mandiri Sandbox ===
        $response = Http::withHeaders([
            'Content-Type'      => 'application/json',
            'X-CLIENT-KEY'      => $clientId,
            'X-PARTNER-ID'      => $partnerId,
            'X-TIMESTAMP'       => $timestamp,
            'X-SIGNATURE'       => $signature,
            'Authorization'     => 'Bearer ' . $accessToken,
        ])->post('https://sandbox.bankmandiri.co.id' . $endpoint, $payload);

        return response()->json([
            'request' => $payload,
            'signature' => $signature,
            'response' => $response->json()
        ]);
    }

    public function getAccessToken()
    {
        // Data credential sandbox
        $clientId = '4351d2b8-8a0c-49b7-8f34-fdc42a4d1ae3';
        $timestamp = Carbon::now()->format("Y-m-d\TH:i:sP"); // Contoh: 2023-04-27T14:59:48+07:00
        $clientSecret = '325fe2bc-7d9b-4e15-a056-c6d6824a9f1f';

        // Format string untuk signature
        $stringToSign = $clientId . "|" . $timestamp;

        // Path ke private key PEM
        $pem = file_get_contents(storage_path('app/keys/API_Portal.pem'));

        // Load private key dan tanda tangani
        try {
            $privateKey = PublicKeyLoader::load($pem, 'a123') // <- password disini
                ->withPadding(RSA::SIGNATURE_PKCS1)
                ->withHash('sha256');

            $signature = base64_encode($privateKey->sign($stringToSign));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to load private key or generate signature', 'detail' => $e->getMessage()]);
        }

        // Kirim request token
        $client = new Client();

        try {
            $response = $client->request('POST', 'https://sandbox.bankmandiri.co.id/openapi/auth/v2.0/access-token/b2b', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'X-TIMESTAMP' => $timestamp,
                    'X-SIGNATURE' => $signature,
                    'X-CLIENT-KEY' => $clientId,
                    'X-CLIENT-SECRET' => $clientSecret,
                ],
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'X-PARTNER-ID' => 'SANDBOX', // Ganti dengan partner ID yang sesuai
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get access token', 'detail' => $e->getMessage()]);
        }
    }


    public function getAccessTokenStatic()
    {
        $url = 'https://sandbox.bankmandiri.co.id/openapi/auth/v2.0/access-token/b2b';

        $headers = [
            'X-Client-Key' => '70285a30-e30a-4587-8y55-ac9d2hba2',
            'X-TIMESTAMP' => '2020-09-07T08:22:05.429+07:00',
            'X-SIGNATURE' => 'L3jR+bsf6ZpmFx9dc8yY5tyw/3dWdsGga9pe6Fgq6sFqRPNmYXntgVZtobZ6tcI1gV6EPda0iemoqVo1z3mk2oX6uUDWkzy6MA+ulBfuWqdetpHY/yjZSh9HtZ5tUA0McLehiktbFvJPnZ5w/PLS6WWAbHYCZ+ZcaCYvY6fUIOkeT5w+M5SzsSi21SJq7UBwlPCSLigm3mxD3N4hdXNle2xrqcMyamJYVkIppLlxywHFktQlD3zn1fwksJeGTxhfkej7UC45oTV6vHi1uMW06j3NM3EuW/ruvPivXcWVAWWjG5YEWZ1QcDmgivWNRegdw05F9Wyc5tQaOCdt9IK+sw==',
            'User-Agent' => 'PostmanRuntime/7.26.3',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $body = [
            'grant_type' => 'client_credentials',
        ];

        $response = Http::withHeaders($headers)
            ->asForm()
            ->post($url, $body);

        // Log debug
        Log::info('Status: ' . $response->status());
        Log::info('Headers: ', $response->headers());
        Log::info('Body: ' . $response->body());

        return $response->body(); // bisa diganti ke ->json() jika sudah valid json
    }
}
