<?php

namespace App\Jobs;

use App\Models\TopUp;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CekTopupStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public TopUp $topup;
    public int $tries = 7;
    public array $backoff = [600, 600, 600, 600, 600, 600];

    public function __construct(TopUp $topup)
    {
        $this->topup = $topup;
    }

    public function handle()
    {
        $this->topup->refresh();

        if ($this->topup->status_bayar === '1') {
            Log::info("TopUp ID {$this->topup->id} sudah dibayar. Job selesai.");
            return;
        }

        $expired = Carbon::parse($this->topup->tgl_akhir_tagihan)->lt(now());
        if ($expired) {
            Log::warning("TopUp ID {$this->topup->id} sudah kadaluarsa, tidak dicek lagi.");
            return;
        }

        $dataToSend = [
            'request_id_' => $this->topup->request_id,
            'nama_' => $this->topup->user->name,
            'nominal_topup_' => $this->topup->nominal,
            'tanggal_akhir_tagihan_' => Carbon::parse($this->topup->tgl_akhir_tagihan)->format('d-m-Y H:i:s'),
        ];

        $apiKey = config('custom.ubisma_api_key');
        $apiUrl = config('custom.ubisma_api_url');

        if (empty($apiUrl)) {
            Log::error("UBISMA API URL belum dikonfigurasi. Tidak bisa mengirim request.");
            return;
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'Accept' => 'application/json',
        ])->asJson()->post($apiUrl, [
            'data' => [$dataToSend],
        ]);

        if ($response->failed()) {
            Log::warning("Gagal menghubungi UBISMA untuk TopUp ID {$this->topup->id}");
            return;
        }

        $ubismaData = $response->json('data') ?? [];

        if (($ubismaData['status_bayar_'] ?? '0') === '1') {
            try {
                $tglBayar = !empty($ubismaData['tanggal_bayar_'])
                    ? Carbon::parse($ubismaData['tanggal_bayar_'])
                    : $this->topup->tgl_bayar;
            } catch (\Exception $e) {
                Log::error("Gagal parsing tanggal_bayar_ untuk TopUp ID {$this->topup->id}: " . json_encode($ubismaData['tanggal_bayar_'] ?? null));
                $tglBayar = $this->topup->tgl_bayar;
            }

            $this->topup->update([
                'status_bayar' => '1',
                'tgl_bayar' => $tglBayar,
            ]);

            Log::info("TopUp ID {$this->topup->id} diupdate: status_bayar=1, tgl_bayar={$tglBayar}");
        } else {
            if ($this->topup->status_bayar !== '1') {
                Log::info("TopUp ID {$this->topup->id} belum dibayar. Akan dicoba ulang jika retry masih tersedia.");
                throw new \Exception("TopUp belum dibayar. Akan dicoba lagi.");
            }
        }
    }
}
