<?php

namespace Database\Factories;

use Facade\Ignition\Support\FakeComposer;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'nama_tenant' => $this->faker->company(),
            'nama_kavling' => $this->faker->companySuffix(),
            'nama_gambar' => $this->faker->imageUrl(),
        ];
    }
}
