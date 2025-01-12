<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ruangan;
use App\Response\ResponseApi;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use App\Helpers\ValidationHelper;
use App\Services\Admin\RuanganService;

class RuanganController extends Controller
{
    protected $ruanganService;

    public function __construct(RuanganService $ruanganService)
    {
        $this->ruanganService = $ruanganService;
    }

    public function index()
    {
        $ruangan = $this->ruanganService->getAll();
        return ResponseApi::success(compact('ruangan'), 'data berhasil diambil');
    }

    public function store(Request $request)
    {
        $error = ValidationHelper::validate($request->all(), ['nama' => 'required']);
        if ($error) return $error;

        $ruangan = $this->ruanganService->create($request->all());

        return $ruangan
            ? ResponseApi::success(compact('ruangan'), 'data berhasil dibuat')
            : ResponseApi::error('data gagal dibuat');
    }

    public function show($id)
    {
        try {
            $ruangan = $this->ruanganService->findById($id);
            return ResponseApi::success(compact('ruangan'), 'data berhasil diambil');
        } catch (ModelNotFoundException $err) {
            return ResponseApi::error('data gagal diambil');
        }
    }

    public function update(Request $request, $id)
    {
        $error = ValidationHelper::validate($request->all(), ['nama' => 'required']);
        if ($error) return $error;

        $ruangan = $this->ruanganService->update($id, $request->all());

        return $ruangan
            ? ResponseApi::success(compact('ruangan'), 'data berhasil diupdate')
            : ResponseApi::error('data gagal diupdate');
    }

    public function destroy($id)
    {
        $ruangan = $this->ruanganService->delete($id);

        return $ruangan
            ? ResponseApi::success(compact('ruangan'), 'data berhasil dihapus')
            : ResponseApi::error('data gagal dihapus');
    }
}