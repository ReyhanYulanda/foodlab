<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Custom Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi kustom milik aplikasi, bisa dipakai untuk menyimpan nilai
    | dari .env agar bisa dipanggil lewat config().
    |
    */

    'request_id_start' => env('REQUEST_ID_START', 5001),
    'ubisma_api_url' => env('UBISMA_API_URL', 'https://dummy.url/api'),
    'ubisma_api_key' => env('UBISMA_API_KEY', ''),
    'mis_api_url' => env('MIS_API_URL', 'https://dummy.mis.api/url'),
    'mis_api_key' => env('MIS_API_KEY', ''),
    'midtrans_request_id' => env('MIDTRANS_REQUEST_ID', 'midtrans_request_id'),
    'midtrans_post_api_url' => env('MIDTRANS_API_URL', 'https://api.midtrans.com/v2/charge'),
    'midtrans_authorization' => env('MIDTRANS_AUTHORIZATION', 'Basic YOUR_BASE64_ENCODED_SERVER_KEY'),
    // 'midtrans_get_api_url' => env('MIDTRANS_API_URL', 'https://api.midtrans.com/v2/status'),

];
