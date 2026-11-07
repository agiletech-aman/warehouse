<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\FnsDetection;
use App\Models\FnsDetection02;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FnsDetectionFiltersTest extends TestCase
{
    use RefreshDatabase;

    private array $session;

    protected function setUp(): void
    {
        parent::setUp();

        config(['fns.history.enabled' => false, 'fns.history.cache_store' => 'array']);

        foreach (['fns_detections', 'fns_detections_02'] as $name) {
            if (! Schema::hasTable($name)) {
                Schema::create($name, function (Blueprint $table) {
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

        $admin = Admin::factory()->create();
        $this->session = ['admin_id' => $admin->id, 'admin_name' => $admin->name, 'admin_email' => $admin->email];
    }

    public function test_data_endpoint_applies_type_and_date_filters(): void
    {
        $this->detection('fire', 'WH-01', '2026-10-05 10:00:00');
        $this->detection('fire', 'WH-01', '2026-10-20 10:00:00');
        $this->detection('smoke', 'WH-01', '2026-10-20 11:00:00');

        $this->withSession($this->session)
            ->getJson('/fns/detections/data?detection_type=fire&from_date=2026-10-10&to_date=2026-10-31')
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.detection_type', 'fire');
    }

    public function test_warehouse_filter_matches_code_and_name(): void
    {
        $this->insertRow('regions', ['id' => 1]);
        $this->insertRow('warehouses', ['region_id' => 1, 'warehouse_code' => 'WH77', 'warehouse_name' => 'Filter Depot']);
        $this->detection('fire', 'WH-77', '2026-10-05 10:00:00');
        $this->detection('fire', 'WH-88', '2026-10-05 10:00:00');

        foreach (['warehouse=wh-77', 'warehouse=Filter%20Depot'] as $query) {
            $this->withSession($this->session)
                ->getJson('/fns/detections/data?' . $query)
                ->assertOk()
                ->assertJsonPath('recordsFiltered', 1)
                ->assertJsonPath('data.0.warehouse_code', 'WH-77');
        }
    }

    public function test_warehouse_filter_matches_warehouse_resolved_from_camera_ip(): void
    {
        $this->insertRow('device_latest_status', ['device_ip' => '10.0.0.5', 'warehouse' => 'Camera Ip Depot']);
        FnsDetection::create(['camera_ip' => '10.0.0.5', 'warehouse_code' => null] + $this->attributes('fire', 'x', '2026-10-05 10:00:00', 'Ip Camera'));
        $this->detection('fire', 'WH-88', '2026-10-05 10:00:00');

        $this->withSession($this->session)
            ->getJson('/fns/detections/data?warehouse=Camera%20Ip')
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.camera_ip', '10.0.0.5');
    }

    public function test_export_snapshot_links_are_absolute(): void
    {
        FnsDetection::create(['snapshot_path' => 'snapshots/a.jpg'] + $this->attributes('fire', 'WH-01', '2026-10-05 10:00:00', 'Camera'));

        $csv = $this->withSession($this->session)->get('/fns/detections/export')->streamedContent();

        $this->assertStringContainsString(url('/storage/snapshots/a.jpg'), $csv);
    }

    public function test_detection_type_options_come_from_the_pages_own_table(): void
    {
        $this->detection('RODENT', 'WH-01', '2026-10-05 10:00:00');
        $this->detection('rodent', 'WH-01', '2026-10-05 11:00:00');
        FnsDetection02::create($this->attributes('smoke', 'WH-02', '2026-10-05 10:00:00', 'Camera'));

        $page = $this->withSession($this->session)->get('/fns/detections')->assertOk();
        $this->assertSame(1, substr_count($page->getContent(), '<option value="rodent">Rodent</option>'));
        $page->assertDontSee('<option value="smoke">', false)->assertDontSee('<option value="weapon">', false);

        $this->withSession($this->session)->get('/fns/detections02')
            ->assertOk()
            ->assertSee('<option value="smoke">Smoke</option>', false)
            ->assertDontSee('<option value="rodent">', false);
    }

    public function test_invalid_filter_values_are_ignored(): void
    {
        $this->detection('fire', 'WH-01', '2026-10-05 10:00:00');

        $this->withSession($this->session)
            ->getJson('/fns/detections/data?from_date=not-a-date&to_date=2026-13-45&warehouse[]=x&detection_type[]=y')
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1);
    }

    public function test_export_streams_only_filtered_rows_and_neutralises_formulas(): void
    {
        $this->detection('fire', 'WH-01', '2026-10-05 10:00:00', '=HYPERLINK("http://evil")');
        $this->detection('smoke', 'WH-01', '2026-10-05 11:00:00', 'Smoke Camera');

        $response = $this->withSession($this->session)->get('/fns/detections/export?detection_type=fire&search=WH-01');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('fns-detections-', $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Detected At', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('Smoke Camera', $csv);
    }

    public function test_export_merges_history_alerts_newest_first_without_fetching_images(): void
    {
        config(['fns.history.enabled' => true, 'fns.history.url' => 'http://history.test/alerts']);
        Http::fake(['history.test/*' => Http::response([
            'totalPages' => 1,
            'data' => [[
                'id' => 1,
                'camera_ip' => 'stream-1',
                'camera_name' => 'History Cam',
                'location_name' => 'History Depot',
                'alert_type' => "{'Fire': 1}",
                'image_path' => 'http://images.test/one.jpg',
                'alert_date_time' => '2026-10-05 10:30:00',
            ]],
        ])]);
        $this->detection('fire', 'WH-01', '2026-10-05 11:00:00', 'Newer Camera');
        $this->detection('fire', 'WH-01', '2026-10-05 10:00:00', 'Older Camera');

        $csv = $this->withSession($this->session)->get('/fns/detections/export')->streamedContent();

        $newer = strpos($csv, 'Newer Camera');
        $history = strpos($csv, 'History Cam');
        $older = strpos($csv, 'Older Camera');

        $this->assertNotFalse($history);
        $this->assertTrue($newer < $history && $history < $older);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'images.test'));

        // A warehouse filter narrows history alerts by their location name.
        $filtered = $this->withSession($this->session)->get('/fns/detections/export?warehouse=History')->streamedContent();
        $this->assertStringContainsString('History Cam', $filtered);
        $this->assertStringNotContainsString('Newer Camera', $filtered);
    }

    public function test_pages_show_filters_and_second_table_export_uses_its_own_rows(): void
    {
        FnsDetection02::create($this->attributes('fire', 'WH-02', '2026-10-05 10:00:00', 'Second Table Camera'));
        $this->detection('fire', 'WH-01', '2026-10-05 10:00:00', 'First Table Camera');

        foreach (['/fns/detections' => 'fns/detections/export', '/fns/detections02' => 'fns/detections02/export'] as $page => $export) {
            $this->withSession($this->session)->get($page)
                ->assertOk()
                ->assertSee('fnsDetectionFilters')
                ->assertSee($export);
        }

        $csv = $this->withSession($this->session)->get('/fns/detections02/export')->streamedContent();

        $this->assertStringContainsString('Second Table Camera', $csv);
        $this->assertStringNotContainsString('First Table Camera', $csv);
    }

    /**
     * Insert a row with placeholders for the required columns this test does not care about.
     */
    private function insertRow(string $table, array $values): void
    {
        foreach (Schema::getColumns($table) as $column) {
            if (! $column['nullable'] && $column['default'] === null && ! $column['auto_increment'] && ! array_key_exists($column['name'], $values)) {
                $values[$column['name']] = str_contains($column['type'], 'int') ? 1 : 'x';
            }
        }

        DB::table($table)->insert($values);
    }

    private function detection(string $type, string $code, string $at, string $camera = 'Camera'): void
    {
        FnsDetection::create($this->attributes($type, $code, $at, $camera));
    }

    private function attributes(string $type, string $code, string $at, string $camera): array
    {
        return [
            'id' => fake()->uuid(),
            'camera_ip' => '192.168.1.101',
            'camera_name' => $camera,
            'warehouse_code' => $code,
            'godown' => 'Godown A',
            'compartment' => 'C2',
            'detection_type' => $type,
            'confidence' => 0.96,
            'snapshot_path' => null,
            'bounding_box' => '1,2,3,4',
            'detected_at' => $at,
        ];
    }
}
