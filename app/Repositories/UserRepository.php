<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    public function getOnlineDrivers()
    {
        return User::where('isOnline', true)
            ->whereHas('roles', function ($q) {
                $q->where('name', 'masbro');
            })
            ->get();
    }
}
