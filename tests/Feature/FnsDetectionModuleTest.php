<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\FnsDetection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FnsDetectionModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('fns_detections')) {
            Schema::create('fns_detections', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('camera_ip', 45);
                $table->string('camera_name');
                $table->string('warehouse_code', 100)->nullable();
                $table->string('godown')->nullable();
                $table->string('compartment')->nullable();
                $table->string('detection_type');
                $table->decimal('confidence', 5, 4);
                $table->string('snapshot_path', 500)->nullable();
                $table->string('bounding_box', 100)->nullable();
                $table->dateTime('detected_at');
            });
        }
    }

    public function test_api_index_filters_and_paginates_detections(): void
    {
        $this->createDetection('person', 'WH-01', 0.96, now()->subMinute());
        $this->createDetection('person', 'WH-01', 0.85, now()->subMinutes(2));
        $this->createDetection('fire', 'WH-02', 0.99, now()->subMinutes(3));

        $this->getJson('/api/fns/detections?detection_type=person&warehouse_code=WH-01&per_page=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('pagination.per_page', 1)
            ->assertJsonPath('pagination.total', 2)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.detection_type', 'person');
    }

    public function test_web_module_lists_filtered_data_and_appears_in_sidebar(): void
    {
        $admin = Admin::factory()->create();
        $session = [
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'admin_email' => $admin->email,
        ];

        $this->createDetection('smoke', 'WH-SMOKE', 0.91, now());
        $this->createDetection('person', 'WH-PERSON', 0.80, now()->subMinute());

        $this->withSession($session)
            ->get('/fns/detections')
            ->assertOk()
            ->assertSee('FNS Detections')
            ->assertSee(route('fns-detections.index'));

        $this->withSession($session)
            ->getJson('/fns/detections/data?draw=4&start=0&length=10&search[value]=smoke&search[regex]=false')
            ->assertOk()
            ->assertJsonPath('draw', 4)
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.detection_type', 'smoke');
    }

    private function createDetection(
        string $type,
        string $warehouseCode,
        float $confidence,
        mixed $detectedAt
    ): FnsDetection {
        return FnsDetection::create([
            'id' => fake()->uuid(),
            'camera_ip' => '192.168.1.101',
            'camera_name' => 'Godown Camera',
            'warehouse_code' => $warehouseCode,
            'godown' => 'Godown A',
            'compartment' => 'Compartment 2',
            'detection_type' => $type,
            'confidence' => $confidence,
            'snapshot_path' => "snapshots/{$type}.jpg",
            'bounding_box' => '120,80,450,620',
            'detected_at' => $detectedAt,
        ]);
    }
}
