<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $user = ['admin'];

        foreach($user as $value){
            User::create([
                'name' =>  $value,
                'email' => $value.'@gmail.com',
                'password'=> bcrypt('As3!xL9@uQ1%vR8#'),
            ])->assignRole($value);
        }
    }
}
