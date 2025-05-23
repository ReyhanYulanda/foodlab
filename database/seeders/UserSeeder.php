<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $users = [
            [
                'name' => 'admin',
                'email' => 'admin@gmail.com',
                'password' => bcrypt('12345678'),
                'role' => 'admin'
            ],
            [
                'name' => 'ubisma',
                'email' => 'ubisma@gmail.com',
                'password' => bcrypt('12345678!'),
                'role' => 'ubisma'
            ]
        ];

        foreach ($users as $user) {
            User::create([
                'name' => $user['name'],
                'email' => $user['email'],
                'password' => $user['password'],
            ])->assignRole($user['role']);
        }
    }
}