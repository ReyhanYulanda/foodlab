<?php

namespace Database\Seeders;

use App\Models\Konfigurrasi\Menu;
use App\Models\Permission;
use App\Traits\HasMenuPermission;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    use HasMenuPermission;

    public function run()
    {
        $mm = Menu::firstOrCreate(['url' => '/role'],[
            'nama' => 'role', 'url' => '/role', 'kategori' => 'KONFIGURASI',
        ]);
        $this->attachMenuPermission($mm, null, ['admin']);

        $mm = Menu::firstOrCreate(['url' => '/permission'],[
            'nama' => 'permission', 'url' => '/permission', 'kategori' => 'KONFIGURASI',
        ]);
        $this->attachMenuPermission($mm, null, ['admin']);

        $mm = Menu::firstOrCreate(['url' => '/menu'],[
            'nama' => 'menu', 'url' => '/menu', 'kategori' => 'KONFIGURASI',
        ]);
        $this->attachMenuPermission($mm, null, ['admin']);
    }
}
