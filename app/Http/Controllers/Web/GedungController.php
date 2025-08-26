<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Gedung;
use App\Models\Ruangan;
use Illuminate\Http\Request;

class GedungController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('read gedung');
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');

        $query = Gedung::query();

        if ($search) {
            $query->where('nama', 'like', "%{$search}%");
        }

        $gedung = $query->paginate($perPage);
        return view('pages.konfigurasi.gedung.index', compact('gedung'));
    }

    public function create()
    {
        return view('pages.konfigurasi.gedung.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required',
            'ongkir' => 'required',
        ]);

        Gedung::create([
            'nama' => $request->nama,
            'ongkir' => $request->ongkir
        ]);

        return redirect()->route('gedung.index')->with(["status" => "success", 'message' => "Gedung berhasil ditambahkan"]);
    }

    public function edit($id)
    {
        $gedung = Gedung::find($id);
        return view('pages.konfigurasi.gedung.edit', compact('gedung'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama' => 'required',
            'ongkir' => 'required',
        ]);

        Gedung::find($id)->update([
            'nama' => $request->nama,
            'ongkir' => $request->ongkir
        ]);

        return redirect()->route('gedung.index')->with(["status" => "success", 'message' => "Gedung berhasil diupdate"]);
    }

    public function destroy($id)
    {
        Gedung::find($id)->delete();
        return redirect()->route('gedung.index')->with(["status" => "success", 'message' => "Gedung berhasil dihapus"]);
    }
}
