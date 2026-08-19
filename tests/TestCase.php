<?php

namespace Tests;

use App\Models\Admin;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $compiledViews = storage_path('framework/testing-views');
        File::ensureDirectoryExists($compiledViews);
        config(['view.compiled' => $compiledViews]);
    }

    protected function adminSession(): array
    {
        $admin = Admin::factory()->create();

        return [
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'admin_email' => $admin->email,
        ];
    }
}
