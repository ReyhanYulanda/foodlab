<?php

namespace App\Http\Controllers\Web\Konfigurasi;

use App\Http\Controllers\Controller;
use App\Models\Pengaturan;
use Illuminate\Http\Request;

class PengaturanController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10); 
        $search = $request->input('search');

        $query = Pengaturan::query();

        if ($search) {
            $query->where('nama', 'like', "%{$search}%");
        }

        $pengaturan = $query->paginate($perPage);
        return view('pages.konfigurasi.pengaturan.index', compact('pengaturan'));
    }

    public function create()
    {
        return view('pages.konfigurasi.pengaturan.create');
    }

    public function store(Request $request, Pengaturan $pengaturan)
    {
        $request->validate([
            "nama" => 'required',
            "nilai" => 'required'
        ]);

        $pengaturan->nama = $request->nama;
        $pengaturan->nilai = $request->nilai;
        $pengaturan->save();

        return redirect()->route('pengaturan.index')->with(["status" => "success", "messages" => "Pengaturan Berhasil Ditambahkan"]);
    }

    public function edit(Pengaturan $pengaturan)
    {
        return view('pages.konfigurasi.pengaturan.edit', compact('pengaturan'));
    }

    public function update(Request $request, Pengaturan $pengaturan)
    {
        $request->validate([
            "nama" => 'required',
            "nilai" => 'required'
        ]);

        $pengaturan->nama = $request->nama;
        $pengaturan->nilai = $request->nilai;
        $pengaturan->save();

        return redirect()->route('pengaturan.index')->with(["status" => "success", "messages" => "Pengaturan Berhasil Ditambahkan"]);
    }

    public function destroy(Pengaturan $pengaturan)
    {
        try {
            $pengaturan->delete();
            return redirect()->route('pengaturan.index')->with(["status" => "success", "messages" => "Pengaturan Berhasil Dihapus"]);
        } catch (\Throwable $th) {
            return redirect()->route('pengaturan.index')->with(["status" => "failed", "messages" => "Pengaturan Gagal Dihapus"]);
        }
    }
}
