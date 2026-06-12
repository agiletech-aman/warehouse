<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_module_routes_are_available(): void
    {
        $this->get('/devices')
            ->assertStatus(200)
            ->assertSee('Devices');

        $this->get('/devices/create')
            ->assertStatus(200)
            ->assertSee('Add Device');
    }
}
