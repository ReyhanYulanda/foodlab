<?php

namespace App\Http\Controllers\Web\SaldoKoin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SaldoKoin;
use App\Models\TransaksiSaldoKoin;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class SaldoKoinController extends Controller
{
    public function index()
    {
        $this->authorize('read saldo_koin');
        $saldos = SaldoKoin::with('user')->get();
        return view('pages.saldoKoin.index', compact('saldos'));
    }

    public function create()
    {
        $users = User::select('id', 'name', 'email')->get();
        return view('pages.saldoKoin.create', compact('users'));
    }
    

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'jumlah' => 'required|integer|min:0',
            'deskripsi' => 'nullable|string|max:255'
        ]);
    
        // Cek apakah user sudah memiliki saldo
        $saldo = SaldoKoin::firstOrCreate(['user_id' => $request->user_id]);
    
        // Tambah saldo
        $saldo->jumlah += $request->jumlah;
        $saldo->save();
    
        // Simpan transaksi
        TransaksiSaldoKoin::create([
            'user_id' => $request->user_id,
            'jumlah' => $request->jumlah,
            'tipe' => 'masuk',
            'deskripsi' => $request->deskripsi ?? 'Penambahan Saldo Koin'
        ]);
    
        return redirect()->route('saldoKoin.index')->with('success', 'Saldo koin berhasil diperbarui.');
    }
    
    

    public function edit($id)
    {
        $saldo = SaldoKoin::findOrFail($id);
        $users = User::all();
        return view('pages.saldoKoin.edit', compact('saldo', 'users'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'jumlah' => 'required|integer|min:0',
        ]);

        $saldo = SaldoKoin::findOrFail($id);
        $saldo->update([
            'jumlah' => $request->jumlah,
        ]);

        return redirect()->route('saldoKoin.index')->with('success', 'Saldo koin berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $saldo = SaldoKoin::findOrFail($id);
        $saldo->delete();

        return redirect()->route('saldoKoin.index')->with('success', 'Saldo koin berhasil dihapus.');
    }

    public function riwayatTransaksi($user_id)
    {
        // Ambil riwayat transaksi berdasarkan user_id
        $transaksi = TransaksiSaldoKoin::where('user_id', $user_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('pages.saldoKoin.riwayat', compact('transaksi'));
    }
}