<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_change_password_with_old_password(): void
    {
        $admin = Admin::factory()->create([
            'password' => bcrypt('old-password'),
        ]);

        $response = $this->withSession(['admin_id' => $admin->id])
            ->post('/admin/change-password', [
                'old_password' => 'old-password',
                'new_password' => 'new-password-123',
                'new_password_confirmation' => 'new-password-123',
            ]);

        $response->assertRedirect('/admin/settings');
        $this->assertTrue(password_verify('new-password-123', $admin->fresh()->password));
    }
}
