<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_reading_module_routes_are_available(): void
    {
        $this->get('/readings')
            ->assertStatus(200)
            ->assertSee('Readings');

        $this->get('/readings/create')
            ->assertStatus(200)
            ->assertSee('Add Reading');
    }
}
