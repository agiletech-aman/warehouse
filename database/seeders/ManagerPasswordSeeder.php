<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ManagerPasswordSeeder extends Seeder
{
    public function run(): void
    {
        $initialPassword = (string) config('services.warehouse_auth.manager_initial_password');
        if ($initialPassword === '') {
            throw new \RuntimeException('WAREHOUSE_MANAGER_INITIAL_PASSWORD must be configured before running this seeder.');
        }

        $password = Hash::make($initialPassword);

        DB::table('warehouses')->update([
            'password' => $password,
        ]);

        DB::table('regions')->update([
            'password' => $password,
        ]);
    }
}
