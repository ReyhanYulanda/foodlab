<?php

namespace App\Services\Transaksi\Actions;

use App\Models\SaldoKoin;
use App\Models\TopUp;
use App\Models\TransaksiSaldoKoin;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GetTopUpAction
{
    public function execute($kodeBayar)
    {
        $topup = TopUp::where('kode_bayar', $kodeBayar)->first();

        if (!$topup) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data TopUp tidak ditemukan.'
            ], 404);
        }

        $apiKey = config('custom.ubisma_api_key');
        $apiUrl = config('custom.ubisma_api_url') . "?q=" . $kodeBayar;

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'Accept' => 'application/json',
        ])->get($apiUrl);

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghubungi UBISMA.',
                'debug' => $response->body()
            ], $response->status());
        }

        $ubismaDataList = $response->json('data');

        if (!$ubismaDataList || empty($ubismaDataList)) {
            Log::info("Data tagihan tidak ditemukan di Ubisma untuk kode_bayar {$kodeBayar}");
            return response()->json([
                'status' => 'success',
                'data' => [
                    'topup' => $topup,
                    'status_ubisma' => 'Data tagihan belum di-push atau tidak ditemukan di UBISMA.'
                ]
            ]);
        }

        $tagihanItems = array_filter($ubismaDataList, function ($item) use ($kodeBayar) {
            return isset($item['kode_bayar_mandiri_']) && $item['kode_bayar_mandiri_'] == $kodeBayar;
        });

        if (empty($tagihanItems)) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'topup' => $topup,
                    'status_ubisma' => 'Data tagihan belum di-push atau tidak ditemukan di UBISMA.'
                ]
            ]);
        }

        $tagihanItem = reset($tagihanItems);

        $statusLunas = $tagihanItem['status_lunas_'] ?? null;

        if ($statusLunas === '1') {
            if ($topup->status !== 'terbayar') {
                $topup->update([
                    'status' => 'terbayar'
                ]);

                $saldoKoin = SaldoKoin::firstOrCreate(
                    ['user_id' => $topup->user_id],
                    ['jumlah' => 0]
                );
                $saldoKoin->increment('jumlah', $topup->nominal);

                TransaksiSaldoKoin::create([
                    'user_id' => $topup->user_id,
                    'jumlah' => $topup->nominal,
                    'tipe' => 'masuk',
                    'deskripsi' => "Top Up via UBISMA (Kode Bayar: {$kodeBayar})"
                ]);

                Log::info("TopUp berhasil: ID {$topup->id}, Nominal ditambahkan: {$topup->nominal}");
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'topup' => $topup,
                'ubisma_response' => $tagihanItem
            ]
        ]);
    }
}
