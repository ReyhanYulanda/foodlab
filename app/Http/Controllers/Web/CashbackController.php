<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Cashback;
use App\Models\Ruangan;
use Illuminate\Http\Request;

class CashbackController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('read cashback');
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');

        $query = Cashback::query();

        if ($search) {
            $query->where('nama', 'like', "%{$search}%");
        }

        $cashback = $query->paginate($perPage);
        return view('pages.konfigurasi.cashback.index', compact('cashback'));
    }

    public function create()
    {
        return view('pages.konfigurasi.cashback.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'value' => 'required',
            'quantity' => 'required',
            'minimal_beli' => 'required',
            'max_cashback' => 'required',
            'max_used' => 'required',
            'start_date' => 'required',
            'end_date' => 'required|date|after:start_date',
        ], [
            'end_date.after' => 'Tanggal berakhir harus setelah tanggal mulai.',
        ]);

        Cashback::create([
            'value' => $request->value,
            'quantity' => $request->quantity,
            'is_valid' => 1,
            'referral_code' => uniqid(),
            'minimal_beli' => $request->minimal_beli,
            'max_cashback' => $request->max_cashback,
            'max_used' => $request->max_used,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date
        ]);

        return redirect()->route('cashback.index')->with(["status" => "success", 'message' => "Cashback berhasil ditambahkan"]);
    }

    public function edit($id)
    {
        $cashback = Cashback::find($id);
        return view('pages.konfigurasi.cashback.edit', compact('cashback'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'value' => 'required',
            'quantity' => 'required',
            'minimal_beli' => 'required',
            'is_valid' => 'required',
            'max_cashback' => 'required',
            'max_used' => 'required',
            'start_date' => 'required',
            'end_date' => 'required|date|after:start_date',
        ], [
            'end_date.after' => 'Tanggal berakhir harus setelah tanggal mulai.',
        ]);

        Cashback::findOrFail($id)->update([
            'value' => $request->value,
            'quantity' => $request->quantity,
            'minimal_beli' => $request->minimal_beli,
            'max_cashback' => $request->max_cashback,
            'is_valid' => $request->is_valid,
            'max_used' => $request->max_used,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date
        ]);

        return redirect()->route('cashback.index')->with(["status" => "success", 'message' => "Cashback berhasil diupdate"]);
    }

    public function destroy($id)
    {
        Cashback::find($id)->delete();
        return redirect()->route('cashback.index')->with(["status" => "success", 'message' => "Cashback berhasil dihapus"]);
    }
}
