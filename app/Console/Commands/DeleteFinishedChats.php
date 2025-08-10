<?php

namespace App\Console\Commands;

use App\Models\ChatMessage;
use App\Models\Transaksi;
use Illuminate\Console\Command;

class DeleteFinishedChats extends Command
{
    protected $signature = 'chat:delete-finished';
    protected $description = 'Hapus semua chat dari transaksi selesai atau refund_selesai';

    public function handle()
    {
        $transaksiIds = Transaksi::whereIn('status', ['selesai', 'refund_selesai'])
            ->pluck('id');

        if ($transaksiIds->isEmpty()) {
            $this->info('Tidak ada chat yang perlu dihapus.');
            return;
        }

        $deleted = ChatMessage::whereIn('transaksi_id', $transaksiIds)->delete();

        $this->info("Berhasil menghapus {$deleted} chat dari transaksi selesai/refund.");
    }
}
