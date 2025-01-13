<?php

namespace App\Services\Kelola;

use App\Models\Menus;
use App\Models\Tenants;

class TenantService
{
    public function getTenantData()
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