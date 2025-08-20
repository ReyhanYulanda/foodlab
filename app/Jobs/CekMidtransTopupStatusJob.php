<?php

namespace App\Jobs;

use App\Models\TopUp;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CekMidtransTopupStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public TopUp $topup;
    public int $tries = 3; // hanya 3x
    public array $backoff = [300, 300]; // 5 menit sekali (300 detik)

    public function __construct(TopUp $topup)
    {
        $this->topup = $topup;
    }

    public function handle()
    {
        $this->topup->refresh();

        if ($this->topup->status_bayar === 'settlement') {
            Log::info("TopUp ID {$this->topup->id} sudah dibayar. Job selesai.");
            return;
        }

        if (Carbon::parse($this->topup->tgl_akhir_tagihan)->lt(now())) {
            Log::warning("TopUp ID {$this->topup->id} sudah kadaluarsa, stop pengecekan.");
            return;
        }

        $apiUrl = config('custom.midtrans_get_api_url') . '/' . $this->topup->midtrans_request_id . '/status';
        $serverKey = config('custom.midtrans_server_key');
        $authHeader = 'Basic ' . base64_encode($serverKey . ':');

        // pakai cURL
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
            Log::error("cURL error saat cek status Midtrans untuk TopUp ID {$this->topup->id}: {$curlError}");
            return;
        }

        curl_close($ch);

        $midtransData = json_decode($response, true);

        if ($httpCode >= 400) {
            Log::warning("Gagal cek status Midtrans (HTTP {$httpCode}) untuk TopUp ID {$this->topup->id}", [
                'response' => $midtransData
            ]);
            return;
        }

        $transactionStatus = $midtransData['transaction_status'] ?? null;

        if ($transactionStatus === 'settlement') {
            $this->topup->update([
                'status_bayar' => 'settlement',
                'tgl_bayar' => now(),
            ]);
            Log::info("TopUp ID {$this->topup->id} sukses dibayar.");
        } elseif ($transactionStatus === 'expire' || $transactionStatus === 'cancel') {
            $this->topup->update([
                'status_bayar' => $transactionStatus,
            ]);
            Log::warning("TopUp ID {$this->topup->id} status: {$transactionStatus} (update DB).");
        } else {
            Log::info("TopUp ID {$this->topup->id} masih status {$transactionStatus}. Akan dicoba ulang.");
            throw new \Exception("Belum settlement, retry lagi.");
        }
    }
}
