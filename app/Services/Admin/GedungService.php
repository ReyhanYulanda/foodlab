<?php

namespace App\Services;

use App\Models\Gedung;

class GedungService
{
    public function getAll()
    {
        return Gedung::all();
    }

    public function create($data)
    {
        return Gedung::create($data);
    }

    public function findById($id)
    {
        return Gedung::findOrFail($id);
    }

    public function update($id, $data)
    {
        return Gedung::where('id', $id)->update($data);
    }

    public function delete($id)
    {
        return Gedung::find($id)->delete();
    }
}