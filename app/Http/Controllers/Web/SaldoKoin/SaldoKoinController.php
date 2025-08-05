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
}
