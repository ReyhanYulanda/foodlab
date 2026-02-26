<?php

namespace App\Services\SaldoKoin\Actions;

use App\Repositories\SaldoKoinRepository;
use App\Repositories\TransaksiSaldoKoinRepository;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TransferCoinAction
{
    protected SaldoKoinRepository $saldoRepo;
    protected TransaksiSaldoKoinRepository $transaksiRepo;

    public function __construct(
        SaldoKoinRepository $saldoRepo,
        TransaksiSaldoKoinRepository $transaksiRepo
    ) {
        $this->saldoRepo = $saldoRepo;
        $this->transaksiRepo = $transaksiRepo;
    }

    public function execute($request)
    {
        $validator = Validator::make($request->all(), [
            'sender_id' => 'required|exists:users,id',
            'receiver_id' => 'required|exists:users,id|different:sender_id',
            'jumlah' => 'required|integer|min:1',
        ]);

        $validator->validate();

        return DB::transaction(function () use ($request) {
            $senderSaldo = $this->saldoRepo->getByUserIdForUpdate($request->sender_id);
            $receiverSaldo = $this->saldoRepo->getByUserIdForUpdate($request->receiver_id);

            if (!$senderSaldo || $senderSaldo->jumlah < $request->jumlah) {
                return [
                    'error' => true,
                    'status' => 422,
                    'message' => 'Saldo pengirim tidak mencukupi'
                ];
            }

            // Ambil user detail
            $senderUser = User::find($request->sender_id);
            $receiverUser = User::find($request->receiver_id);

            // Update saldo
            $senderSaldo->decrement('jumlah', $request->jumlah);
            $receiverSaldo ? $receiverSaldo->increment('jumlah', $request->jumlah)
                : $this->saldoRepo->create([
                    'user_id' => $request->receiver_id,
                    'jumlah' => $request->jumlah
                ]);

            // Catat transaksi pengirim (keluar)
            $this->transaksiRepo->create([
                'user_id' => $request->sender_id,
                'jumlah' => $request->jumlah * (-1),
                'tipe' => 'keluar',
                'deskripsi' => 'Transfer koin ke ' . $receiverUser->name
            ]);

            // Catat transaksi penerima (masuk)
            $this->transaksiRepo->create([
                'user_id' => $request->receiver_id,
                'jumlah' => $request->jumlah,
                'tipe' => 'masuk',
                'deskripsi' => 'Menerima koin dari ' . $senderUser->name
            ]);

            return [
                'error' => false,
                'message' => 'Transfer sebesar Rp ' . number_format($request->jumlah, 0, ',', '.') . ' berhasil',
                'data' => [
                    'sender' => $senderSaldo->fresh(),
                    'receiver' => $receiverSaldo ? $receiverSaldo->fresh() : $this->saldoRepo->getByUserIdForUpdate($request->receiver_id)
                ]
            ];
        });
    }
}
