<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Ruangan;

class RuanganTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_it_can_check_if_ruangan_has_more_than_9999_records(): void
    {
        Ruangan::factory()->count(10000)->create();

        $this->assertGreaterThan(9999, Ruangan::count());
    }
}
