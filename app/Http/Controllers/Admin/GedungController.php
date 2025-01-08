<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Response\ResponseApi;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use App\Helpers\ValidationHelper;
use App\Services\GedungService;

class GedungController extends Controller
{
    protected $gedungService;

    public function __construct(GedungService $gedungService)
    {
        $this->gedungService = $gedungService;
    }

    public function index()
    {
        $gedung = $this->gedungService->getAll();
        return ResponseApi::success(compact('gedung'), 'data berhasil diambil');
    }

    public function store(Request $request)
    {
        $error = ValidationHelper::validate($request->all(), ['nama' => 'required']);
        if ($error) return $error;

        $gedung = $this->gedungService->create($request->all());

        return $gedung
            ? ResponseApi::success(compact('gedung'), 'data berhasil dibuat')
            : ResponseApi::error('Gagal Membuat Gedung');
    }

    public function show($id)
    {
        try{
            $gedung = $this->gedungService->findById($id);
            return ResponseApi::success(compact('gedung'), 'data berhasil diambil');
        }catch(ModelNotFoundException $err){
            return ResponseApi::error('data tidak ditemukan');
        }
    }

    public function update(Request $request, $id)
    {
        $error = ValidationHelper::validate($request->all(), ['nama' => 'required']);
        if ($error) return $error;

        $gedung = $this->gedungService->update($id, $request->all());

        return $gedung
            ? ResponseApi::success(compact('gedung'), 'data berhasil diupdate')
            : ResponseApi::error('Gagal Membuat Gedung');
    }

    public function destroy($id)
    {
        $gedung = $this->gedungService->delete($id);
        if($gedung){
            return ResponseApi::success(compact('gedung'), 'data berhasil dihapus');
        }else{
            return ResponseApi::error('gagal menghapus gedung');
        }
    }
}