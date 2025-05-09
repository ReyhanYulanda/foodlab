<?php

namespace Database\Seeders;

use App\Models\Gedung;
use App\Models\Ruangan;
use Illuminate\Database\Seeder;

class GedungSeeder extends Seeder
{
    public function run()
    {
        $gedungList = ['D4', 'D3', 'PASCA', 'SAW', 'TC'];

        foreach ($gedungList as $nama) {
            Gedung::create(['nama' => $nama]);
        }
    }
}
