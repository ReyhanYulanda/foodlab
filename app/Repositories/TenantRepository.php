<?php


namespace App\Repositories;


use App\Models\Tenants;


class TenantRepository
{
    public function getByUser($userId)
    {
        return Tenants::where('user_id', $userId)->first();
    }


    public function getById($id)
    {
        return Tenants::find($id);
    }
}
