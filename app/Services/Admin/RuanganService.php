<?php

namespace App\Services\Admin;

use App\Models\Ruangan;

class RuanganService
{
    public function getAll()
    {
        return Ruangan::all();
    }

    public function create($data)
    {
        return Ruangan::create($data);
    }

    public function findById($id)
    {
        return Ruangan::findOrFail($id);
    }

    public function update($id, $data)
    {
        return Ruangan::where('id', $id)->update($data);
    }

    public function delete($id)
    {
        return Ruangan::find($id)->delete();
    }
}