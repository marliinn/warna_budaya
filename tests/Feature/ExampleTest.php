<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_homepage_is_available(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Setiap Daerah Punya Cerita.')
            ->assertSee(route('admin.super-entry'), false)
            ->assertSee('Akses Super Admin');
    }
}
