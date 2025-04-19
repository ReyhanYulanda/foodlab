<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = ['admin', 'user', 'tenant', 'kdh'];

        foreach($user as $value){
            User::create([
                'name' =>  $value,
                'email' => $value.'@gmail.com',
                'password'=> bcrypt('i12345678'),
            ])->assignRole($value);
        }
    }
}
