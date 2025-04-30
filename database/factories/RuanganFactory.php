<?php

namespace Database\Factories;

use App\Models\Gedung;
use Illuminate\Database\Eloquent\Factories\Factory;

class RuanganFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => $this->faker->word,
            'gedung_id' => Gedung::factory(),
            'kode_ruangan' => $this->faker->unique()->numerify('R#####'),
            'lat' => $this->faker->latitude,
            'lng' => $this->faker->longitude,
        ];
    }
}
