<?php

namespace App\Helper;

use App\Models\Role;

class RoleHelper
{
    public static function isRoleExist($role_name)
    {
        $roleExist = Role::where('name', $role_name)->exists();;

        if (!$roleExist) {
            throw new \Exception("Role Does Exist");
            // return response()->json([
            //     'status' => 'failed',
            //     'message' => "role {$role_name} tidak tersedia"
            // ]);
        }
    }
}
