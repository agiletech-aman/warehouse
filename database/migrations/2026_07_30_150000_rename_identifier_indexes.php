<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->indexExists('regions', 'regions_frs_id_unique')) {
            Schema::table('regions', function (Blueprint $table) {
                $table->unique('frs_id', 'regions_frs_id_unique');
            });
        }

        if ($this->indexExists('regions', 'regions_uuid_unique')) {
            Schema::table('regions', function (Blueprint $table) {
                $table->dropUnique('regions_uuid_unique');
            });
        }

        if (! $this->indexExists('warehouses', 'warehouses_frs_id_unique')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->unique('frs_id', 'warehouses_frs_id_unique');
            });
        }

        if ($this->indexExists('warehouses', 'warehouses_uuid_unique')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->dropUnique('warehouses_uuid_unique');
            });
        }

        if (! $this->indexExists('warehouses', 'warehouses_region_frs_id_index')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->index('region_frs_id', 'warehouses_region_frs_id_index');
            });
        }

        if ($this->indexExists('warehouses', 'warehouses_region_uuid_foreign')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->dropIndex('warehouses_region_uuid_foreign');
            });
        }
    }

    public function down(): void
    {
        if (! $this->indexExists('regions', 'regions_uuid_unique')) {
            Schema::table('regions', function (Blueprint $table) {
                $table->unique('frs_id', 'regions_uuid_unique');
            });
        }

        if ($this->indexExists('regions', 'regions_frs_id_unique')) {
            Schema::table('regions', function (Blueprint $table) {
                $table->dropUnique('regions_frs_id_unique');
            });
        }

        if (! $this->indexExists('warehouses', 'warehouses_uuid_unique')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->unique('frs_id', 'warehouses_uuid_unique');
            });
        }

        if ($this->indexExists('warehouses', 'warehouses_frs_id_unique')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->dropUnique('warehouses_frs_id_unique');
            });
        }

        if (! $this->indexExists('warehouses', 'warehouses_region_uuid_foreign')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->index('region_frs_id', 'warehouses_region_uuid_foreign');
            });
        }

        if ($this->indexExists('warehouses', 'warehouses_region_frs_id_index')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->dropIndex('warehouses_region_frs_id_index');
            });
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $index) => $index['name'] === $name);
    }
};
