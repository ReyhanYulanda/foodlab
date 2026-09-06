<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Halaman root mengarahkan tamu ke halaman login.
     *
     * @return void
     */
    public function test_root_redirects_guest_to_login()
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }
}
