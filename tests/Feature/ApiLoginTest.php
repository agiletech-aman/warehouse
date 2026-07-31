<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\Warehouse;
use Database\Seeders\ManagerPasswordSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_manager_can_login_as_wo(): void
    {
        $region = Region::factory()->create();
        $warehouse = Warehouse::create([
            'region_id' => $region->id,
            'warehouse_code' => 'WH-LOGIN',
            'warehouse_name' => 'Login Warehouse',
            'manager_name' => 'Warehouse Manager',
            'manager_email' => 'warehouse@example.com',
            'password' => 'CWC@0001',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'warehouse@example.com',
            'password' => 'CWC@0001',
        ]);

        $response->assertOk()
            ->assertJsonPath('role', 'wo')
            ->assertJsonPath('user.id', $warehouse->frs_id)
            ->assertJsonPath('user.email', 'warehouse@example.com')
            ->assertJsonStructure(['token', 'token_type', 'expires_in']);
        $this->assertSame('wo', $this->tokenPayload($response->json('token'))['role']);
    }

    public function test_region_manager_can_login_as_ro(): void
    {
        Region::factory()->create([
            'manager_name' => 'Region Manager',
            'manager_email' => 'region@example.com',
            'password' => 'CWC@0001',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'region@example.com',
            'password' => 'CWC@0001',
        ]);

        $response->assertOk()
            ->assertJsonPath('role', 'ro')
            ->assertJsonPath('user.email', 'region@example.com')
            ->assertJsonStructure(['token']);
    }

    public function test_static_super_admin_can_login_as_co(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'superadmin@warehouse.com',
            'password' => 'CWC@0001',
        ]);

        $response->assertOk()
            ->assertJsonPath('role', 'co')
            ->assertJsonPath('user.name', 'Super Admin')
            ->assertJsonStructure(['token']);
    }

    public function test_manager_password_seeder_sets_the_shared_password(): void
    {
        $region = Region::factory()->create([
            'password' => 'old-password',
        ]);
        $warehouse = Warehouse::create([
            'region_id' => $region->id,
            'warehouse_code' => 'WH-SEED',
            'warehouse_name' => 'Seed Warehouse',
            'manager_name' => 'Seed Manager',
            'manager_email' => 'seed@example.com',
            'password' => 'old-password',
            'status' => 'active',
        ]);

        $this->seed(ManagerPasswordSeeder::class);

        $this->assertTrue(Hash::check('CWC@0001', $warehouse->fresh()->password));
        $this->assertTrue(Hash::check('CWC@0001', $region->fresh()->password));
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->postJson('/api/login', [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized()
            ->assertJsonPath('status', false);
    }

    private function tokenPayload(string $token): array
    {
        return json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
    }
}
