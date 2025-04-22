<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Gedung;
use App\Models\Ruangan;
use Illuminate\Http\Request;

class RuanganController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('read ruangan');
    
        $query = Ruangan::with('gedung');
    
        if ($search = $request->input('search')) {
            $query->where('nama', 'like', "%$search%")
                ->orWhere('kode_ruangan', 'like', "%$search%")
                ->orWhereHas('gedung', function ($q) use ($search) {
                    $q->where('nama', 'like', "%$search%");
                });
        }
    
        $ruangan = $query->paginate(10);
    
        return view('pages.konfigurasi.ruangan.index', compact('ruangan'));
    }

    public function create()
    {
        $gedung = Gedung::all();
        return view('pages.konfigurasi.ruangan.create', compact('gedung'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required',
            'gedung_id' => 'required',
            'kode_ruangan' => 'required',
        ]);

        Ruangan::create([
            'nama' => $request->nama,
            'gedung_id' => $request->gedung_id,
            'kode_ruangan' => $request->kode_ruangan,
        ]);

        return redirect()->route('ruangan.index')->with(["status" => "success", 'message' => "Data Berhasil Diinputkan"]);
    }

    public function edit($id)
    {
        $gedung = Gedung::all();
        $ruangan = Ruangan::find($id);
        return view('pages.konfigurasi.ruangan.edit', compact('gedung', 'ruangan'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama' => 'required',
            'gedung_id' => 'required',
            'kode_ruangan' => 'required',
        ]);

        Ruangan::find($id)->update([
            'nama' => $request->nama,
            'gedung_id' => $request->gedung_id,
            'kode_ruangan' => $request->kode_ruangan,
        ]);

        return redirect()->route('ruangan.index')->with(["status" => "success", 'message' => "Ruangan berhasil diupdate"]);
    }

    public function destroy($id)
    {
        Ruangan::find($id)->delete();
        return redirect()->route('ruangan.index')->with(["status" => "success", 'message' => "Ruangan berhasil dihapus"]);
    }
}
