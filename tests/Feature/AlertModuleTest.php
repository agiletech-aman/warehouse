<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_module_routes_are_available(): void
    {
        $session = $this->adminSession();

        $this->withSession($session)->get('/alerts')
            ->assertStatus(200)
            ->assertSee('Alerts');

        $this->withSession($session)->get('/alerts/create')
            ->assertStatus(200)
            ->assertSee('Add Alert');
    }
}
