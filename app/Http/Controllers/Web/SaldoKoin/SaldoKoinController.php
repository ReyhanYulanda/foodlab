<?php

namespace App\Http\Controllers\Web\SaldoKoin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SaldoKoin;
use App\Models\TransaksiSaldoKoin;
use App\Models\User;
use App\Services\Firebases;

class SaldoKoinController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('read saldo_koin');

        $perPage = $request->input('per_page', 10);
        $query = SaldoKoin::with('user');

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->whereHas('user', function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            });
        }

        $saldos = $query->paginate($perPage);

        return view('pages.saldoKoin.index', compact('saldos'));
    }

    public function create()
    {
        $this->authorize('create saldo_koin');

        $users = User::select('id', 'name', 'email')->get();
        return view('pages.saldoKoin.create', compact('users'));
    }

    public function store(Request $request, Firebases $firebases)
    {
        $this->authorize('create saldo_koin');

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'jumlah' => 'required|integer|min:0',
            'deskripsi' => 'nullable|string|max:255'
        ]);

        $saldo = SaldoKoin::firstOrCreate(['user_id' => $request->user_id]);

        $saldo->jumlah += $request->jumlah;
        $saldo->save();

        TransaksiSaldoKoin::create([
            'user_id' => $request->user_id,
            'jumlah' => $request->jumlah,
            'tipe' => 'masuk',
            'deskripsi' => $request->deskripsi ?? 'Penambahan Saldo Koin'
        ]);

        $user = User::with('fcmTokens')->find($request->user_id);
        $fcmUserToken = $user ? $user->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];

        if ($user && $user->fcm_token) {
            $firebases
                ->withNotification('Top-up Berhasil', 'Saldo sebesar Rp ' . number_format($request->jumlah, 0, ',', '.') . ' telah ditambahkan ke akun Anda.')
                ->withData([
                    'title' => 'Top-up Berhasil',
                    'body' => 'Saldo sebesar Rp ' . number_format($request->jumlah, 0, ',', '.') . ' telah ditambahkan ke akun Anda.',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                ])->sendToFallback($fcmUserToken);
        }

        return redirect()->route('saldoKoin.index')->with('success', 'Saldo koin berhasil diperbarui.');
    }

    public function riwayatTransaksi($user_id)
    {
        $transaksi = TransaksiSaldoKoin::where('user_id', $user_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('pages.saldoKoin.riwayat', compact('transaksi'));
    }

    public function exportCsv()
    {
        $this->authorize('read saldo_koin');

        $saldos = SaldoKoin::with('user')->get();

        $fileName = 'saldo_koin_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$fileName\"",
        ];

        $callback = function () use ($saldos) {
            $file = fopen('php://output', 'w');

            // BOM for Excel UTF-8 compatibility
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header row
            fputcsv($file, ['No', 'Nama User', 'Email', 'Jumlah Saldo', 'Terakhir Diperbarui']);

            foreach ($saldos as $index => $saldo) {
                fputcsv($file, [
                    $index + 1,
                    $saldo->user->name ?? '-',
                    $saldo->user->email ?? '-',
                    number_format($saldo->jumlah, 0, ',', '.'),
                    $saldo->updated_at ? $saldo->updated_at->format('d-m-Y H:i:s') : '-',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
