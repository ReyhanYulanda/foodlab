<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ruangan;
use App\Response\ResponseApi;
use Illuminate\Http\Request;

class RuanganController extends Controller
{
    public function index()
    {
        $ruangan = Ruangan::with('gedung')
            ->orderBy('kode_ruangan', 'asc')
            ->get();

        return ResponseApi::success(compact('ruangan'), 'data berhasil diambil');
    }
}
