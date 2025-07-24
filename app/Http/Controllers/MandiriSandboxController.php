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
use DateTime;
use DateTimeZone;

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
            'X-TIMESTAMP'       => '2025-07-23T22:08:04.848+07:00',
            'X-SIGNATURE'       => 'D7xHrUQS6jasQSHgWKe73vOuagQasML/bVBsKPgYG3oP8ntNOL5fNrN6QfYWl9mEb3cTlfGpHkgZ0j6zwqqZpfWB2LuT40l94JCfgISl2Kf8+4P98AOAtcW391EKEhdPxqr9Q70ajT3mbaIYp17dCzMM7SzbNL6fluyOnhMGk4qA/2cPiRj6MSWO4qSgJYpzcbXVuuKN1hutcVd6QbTt2piGoHDJkL1e49Bh8r/I9BDQ95YDG9q7QUpRukFzCX8bA1ZX9oGbGpLkLTluHKK/gyptri3k2F2/fMXUa2JQG6NXvBYHN17xb0I/MUuaPXK3Miz5dS4AEDBlzJALZwNBDw==',
            'Authorization'     => 'Bearer ' . $accessToken,
        ])->post('https://sandbox.bankmandiri.co.id' . $endpoint, $payload);

        return response()->json([
            'request' => $payload,
            'signature' => $signature,
            'response' => $response->json()
        ]);
    }

    public function getSignature()
    {
        // Path file private key
        $private_key_path = storage_path('app/keys/API_Portal.pem'); // sesuaikan lokasi file
        $password = 'a123';

        // Generate X-Timestamp
        $timestamp = new DateTime();
        $timestamp->setTimeZone(new DateTimeZone('Asia/Jakarta'));
        $x_timestamp = $timestamp->format('c');

        // Client ID
        $client_id = '4351d2b8-8a0c-49b7-8f34-fdc42a4d1ae3';
        $data = $client_id . '|' . $x_timestamp;
        $rsa_algorithm = OPENSSL_ALGO_SHA256;

        // Load private key
        $privatekey_file = file_get_contents($private_key_path);
        $privatekey = openssl_pkey_get_private($privatekey_file, $password);

        if (!$privatekey) {
            return response()->json([
                'error' => 'Failed to load private key',
                'openssl_error' => openssl_error_string()
            ], 500);
        }

        // Generate signature
        if (!openssl_sign($data, $signature, $privatekey, $rsa_algorithm)) {
            return response()->json([
                'error' => 'Failed to sign data',
                'openssl_error' => openssl_error_string()
            ], 500);
        }

        $signature_base64 = base64_encode($signature);

        return response()->json([
            'timestamp' => $x_timestamp,
            'data' => $data,
            'signature' => $signature_base64,
        ]);
    }

    public function getAccessToken()
    {
        $client_id = '4351d2b8-8a0c-49b7-8f34-fdc42a4d1ae3';
        $client_secret = '325fe2bc-7d9b-4e15-a056-c6d6824a9f1f';

        $timestamp = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
        $x_timestamp = $timestamp->format('Y-m-d\TH:i:s.vP');
        // $x_timestamp .= '+0700'; // → 2025-07-23T22:14:30.431+0700 ✅


        $http_method = 'POST';
        $endpoint_path = '/openapi/auth/v2.0/access-token/b2b';
        $form_data = 'grant_type=client_credentials';

        $binary_sha256 = hash('sha256', $form_data, true);
        $hex_sha256 = strtolower(bin2hex($binary_sha256));

        $signature_raw = 'POST:/openapi/auth/v2.0/access-token/b2b:' . $client_id . ':' . $hex_sha256 . ':' . $x_timestamp;
        $signature_hmac = hash_hmac('sha512', $signature_raw, $client_secret, true);
        $x_signature = base64_encode($signature_hmac);

        $base_url = 'https://sandbox.bankmandiri.co.id';

        // Request
        $response = Http::withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded',
            'X-CLIENT-KEY' => $client_id,
            'X-TIMESTAMP' => $x_timestamp,
            'X-SIGNATURE' => $x_signature,
            'User-Agent' => 'PostmanRuntime/7.26.3',
        ])->asForm()->post($base_url . $endpoint_path, [
            'grant_type' => 'client_credentials', // ✅ HARUS pakai underscore
        ]);


        // Log untuk debugging
        Log::info('Mandiri Access Token Response', [
            'timestamp' => $x_timestamp,
            'signature_raw' => $signature_raw,
            'x_signature' => $x_signature,
            'hex_sha256' => $hex_sha256,
            'http_code' => $response->status(),
            'body' => $response->body(),
        ]);

        // Response handling
        if ($response->failed()) {
            return response()->json([
                'error' => 'Failed to retrieve access token',
                'details' => $response->json(),
                'raw_body' => $response->json(),
            ], $response->status());
        }

        $json = $response->json();

        return response()->json([
            'access_token' => $json['accessToken'] ?? null,
            'expires_in' => $json['expiresIn'] ?? null,
            'response' => $json
        ]);
    }


    public function getTransactionSignature()
    {
        // === Setup ===
        $http_method = 'POST';
        $endpoint_url = '/openapi/transaction/v1.0/transfer-va/create-va';

        // Dummy Access Token (dari dokumentasi, sesuaikan jika dynamic)
        $access_token = 'eyJraWQiOiJzc29zIiwiYWxnIjoiUlM1MTIifQ.eyJzdWIiOiIwMzBiM2QzMS00MjQ2LTQ1MzMtYTEyMS0wYTA4NjExZWJmOTgiLCJhdWQiOiJtYW5kaXJpLWF1ZCIsImNsaWVudElkIjoiN2IyNzRkNzktZTYzOC00YzBkLTk1YmEtNWZiMWU1NWFiODFhIiwic2lnbiI6IlgtUEFSVE5FUi1JRCIsImlzcyI6Im1hbmRpcmktand0IiwicGFydG5lcklkIjoiU0FOREJPWCIsImV4cCI6MTY4MDUxMTE4OSwiaWF0IjoxNjgwNTEwMjg5fQ.HjOZ2wVMnoFjvy6MFgQwCpKbdfZ3iCCONsN4t1Q1ONeIY8_ctM1-V_BnmuAAZUBSysrcR8i3llAZJ_k_90jS-DE2wt6eYtwBsfZamJP6t4rh8Hf_vKRDlQ-vjLNRHdFJizuIMJpuWYa9OYHV4qhkI7wYmpc_4JNZV3DjifqoG_AydZpxksFDNTuUitWxVTDD-k-ogrRUpZpH8eBMu3aEbAe_m6Grlk868NJVHogLURy0VNtnPLu6DRz7Z4veB6903h8aljSxKPC4hRJTBkQBaBJCtTm2pSL-IKkesIUNryxzmW2Y_0Ww7PzGFFi0OLBlqxNkvObTq5ndzbi4392t0Q';

        // Client Secret (dari kamu)
        $client_secret = 'fd04e03e-76db-4403-97f4-1c2abc76764c';

        // Payload JSON (simulasi)
        $data_json = [
            "partnerServiceId" => "89661",
            "customerNo" => "8966112900000391",
            "virtualAccountNo" => "8966112900000391",
            "virtualAccountName" => "Jokul Doe",
            "virtualAccountEmail" => "jokul@email.com",
            "virtualAccountPhone" => "6281828384858",
            "trxId" => "abcd12346",
            "totalAmount" => [
                "value" => "75000.00",
                "currency" => "IDR"
            ],
            "billDetails" => [
                [
                    "billAmount" => [
                        "value" => "75000.00",
                        "currency" => "IDR"
                    ]
                ]
            ],
            "expiredDate" => "2022-12-14T23:59:59+07:00"
        ];

        // Minify JSON
        $json_minified = json_encode($data_json, JSON_UNESCAPED_SLASHES);

        // SHA-256 hash binary → hex → lowercase
        $binary_sha256 = hash('sha256', $json_minified, true);
        $hex_sha256 = strtolower(bin2hex($binary_sha256));

        // Timestamp
        $dt = new DateTime();
        $dt->setTimezone(new DateTimeZone('Asia/Jakarta'));
        $timestamp = $dt->format('c');

        // Build signature base string
        $signature_base = $http_method . ':' . $endpoint_url . ':' . $access_token . ':' . $hex_sha256 . ':' . $timestamp;

        // SHA512 HMAC using client secret
        $hmac_sha512 = hash_hmac('sha512', $signature_base, $client_secret, true);
        $x_signature = base64_encode($hmac_sha512);

        // Response
        return response()->json([
            'timestamp' => $timestamp,
            'minified_json' => $json_minified,
            'hex_sha256' => $hex_sha256,
            'signature_base_string' => $signature_base,
            'x-signature' => $x_signature
        ]);
    }
}
