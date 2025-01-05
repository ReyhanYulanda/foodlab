<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gedung;
use App\Response\ResponseApi;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GedungController extends Controller
{
    public function index()
    {
        $gedung = Gedung::all();
        return ResponseApi::success(compact('gedung'), 'data berhasil diambil');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'required',
        ]);

        if($validator->fails()){
            return ResponseApi::error($validator->errors()->all(), 403);
        }

        $gedung = Gedung::create($request->all());

        if($gedung){
            return ResponseApi::success(compact('gedung'), 'data berhasil dibuat');
        }else{
            return ResponseApi::error('Gagal Membuat Gedung');
        }
    }

    public function show($id)
    {
        try{
            $gedung = Gedung::findOrFail($id);
            return ResponseApi::success(compact('gedung'), 'data berhasil diambil');
        }catch(ModelNotFoundException $err){
            return ResponseApi::error('data tidak ditemukan');
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'required',
        ]);

        if($validator->fails()){
            return ResponseApi::error($validator->errors()->all());
        }

        $gedung = Gedung::where('id', $id)->update($request->all());

        if($gedung){
            return ResponseApi::success(compact('gedung'), 'data berhasil diupdate');
        }else{
            return ResponseApi::error('gagal update gedung');
        }
    }

    public function destroy($id)
    {
        $gedung = Gedung::find($id)->delete();
        if($gedung){
            return ResponseApi::success(compact('gedung'), 'data berhasil dihapus');
        }else{
            return ResponseApi::error('gagal menghapus gedung');
        }
    }
}
