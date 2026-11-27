<?php

namespace Tests\Feature\Workstations;

use App\Workstations\Models\{Command, Event, InstallToken, Workstation};
use App\Workstations\Support\AgentProtocol;

class AgentApiTest extends WorkstationTestCase
{
    // ── bootstrap ────────────────────────────────────────────────────────

    public function test_bootstrap_rejects_key_in_query_string(): void
    {
        $this->release('1.0.0', ['is_latest' => true]);
        $this->get('/api/agent/bootstrap/WH1?key=boot-secret')->assertStatus(401);
    }

    public function test_bootstrap_with_header_key_renders_installer(): void
    {
        $rel = $this->release('1.0.0', ['is_latest' => true]);
        $res = $this->get('/api/agent/bootstrap/WH1', ['Authorization' => 'Bearer boot-secret'])->assertOk();
        $sh = $res->getContent();
        $this->assertStringContainsString("WH='WH1'", $sh);
        $this->assertStringContainsString("SHA256='{$rel->sha256}'", $sh);
        $this->assertStringContainsString("PUBKEY='{$this->publicKey}'", $sh);
        $this->assertStringContainsString('pkeyutl -verify', $sh);
        $this->assertStringNotContainsString('{{', $sh);
    }

    public function test_bootstrap_with_install_token_and_warehouse_mismatch(): void
    {
        $this->release('1.0.0', ['is_latest' => true]);
        InstallToken::create(['token_hash' => AgentProtocol::hashToken('wsi_abc'), 'warehouse_code' => 'WH1',
            'max_uses' => 1, 'expires_at' => now()->addHour()]);
        $this->get('/api/agent/bootstrap/WH1', ['Authorization' => 'Bearer wsi_abc'])->assertOk();
        $this->get('/api/agent/bootstrap/WH2', ['Authorization' => 'Bearer wsi_abc'])->assertStatus(403);
    }

    public function test_bootstrap_rejects_expired_install_token(): void
    {
        $this->release('1.0.0', ['is_latest' => true]);
        InstallToken::create(['token_hash' => AgentProtocol::hashToken('wsi_old'), 'warehouse_code' => 'WH1',
            'max_uses' => 1, 'expires_at' => now()->subMinute()]);
        $this->get('/api/agent/bootstrap/WH1', ['Authorization' => 'Bearer wsi_old'])->assertStatus(401);
    }

    public function test_bootstrap_refuses_when_latest_release_unsigned(): void
    {
        $this->release('1.0.0', ['is_latest' => true, 'signature' => null]);
        $this->get('/api/agent/bootstrap/WH1', ['X-API-Key' => 'boot-secret'])->assertStatus(409);
    }

    public function test_bootstrap_is_rate_limited(): void
    {
        config(['workstations.bootstrap_per_minute' => 2]);
        for ($i = 0; $i < 2; $i++) {
            $this->get('/api/agent/bootstrap/WH1')->assertStatus(401);
        }
        $this->get('/api/agent/bootstrap/WH1')->assertStatus(429);
    }

    // ── check-in states ─────────────────────────────────────────────────

    public function test_first_checkin_registers_pending_without_token(): void
    {
        $this->postJson('/api/agent/checkin', ['workstation_id' => 'ws-001', 'version' => '1.0.0'], ['X-API-Key' => 'boot-secret'])
            ->assertOk()->assertJson(['status' => 'pending'])->assertJsonMissing(['token']);
        $w = Workstation::where('workstation_id', 'ws-001')->first();
        $this->assertSame('pending', $w->status);
        $this->assertNull($w->token_hash);
        $this->assertTrue(Event::where('workstation_id', 'ws-001')->where('type', 'registered')->exists());
    }

    public function test_unknown_workstation_without_key_is_rejected(): void
    {
        $this->postJson('/api/agent/checkin', ['workstation_id' => 'ws-001'])->assertStatus(401);
        $this->assertSame(0, Workstation::count());
    }

    public function test_install_token_registers_into_its_warehouse_and_is_consumed(): void
    {
        $t = InstallToken::create(['token_hash' => AgentProtocol::hashToken('wsi_one'), 'warehouse_code' => 'WH9',
            'max_uses' => 1, 'expires_at' => now()->addHour()]);
        $this->postJson('/api/agent/checkin', ['workstation_id' => 'ws-a'], ['X-API-Key' => 'wsi_one'])->assertOk();
        $this->assertSame('WH9', Workstation::where('workstation_id', 'ws-a')->value('warehouse_code'));
        $this->assertSame(1, $t->fresh()->uses);
        // used up: a second machine cannot enroll with it
        $this->postJson('/api/agent/checkin', ['workstation_id' => 'ws-b'], ['X-API-Key' => 'wsi_one'])->assertStatus(401);
    }

    public function test_approved_checkin_issues_hashed_token_once(): void
    {
        $this->workstation('ws-001', ['status' => 'approved']);
        $res = $this->postJson('/api/agent/checkin', ['workstation_id' => 'ws-001'], ['X-API-Key' => 'boot-secret'])
            ->assertOk()->assertJson(['status' => 'approved']);
        $token = $res->json('token');
        $this->assertStringStartsWith('wst_', $token);
        $stored = Workstation::where('workstation_id', 'ws-001')->value('token_hash');
        $this->assertSame(hash('sha256', $token), $stored);
        $this->assertNotSame($token, $stored);

        $this->postJson('/api/agent/checkin', ['workstation_id' => 'ws-001'], ['Authorization' => "Bearer $token"])
            ->assertOk()->assertJsonMissingPath('token');
        // a wrong token without a valid key is still rejected
        $this->postJson('/api/agent/checkin', ['workstation_id' => 'ws-001'], ['Authorization' => 'Bearer wst_wrong'])->assertStatus(401);
    }

    public function test_agent_that_lost_its_token_gets_a_new_one_with_valid_key(): void
    {
        [, $old] = $this->approvedWithToken('ws-001');
        // restart: no Bearer, only the enrollment key
        $new = $this->postJson('/api/agent/checkin', ['workstation_id' => 'ws-001'], ['X-API-Key' => 'boot-secret'])
            ->assertOk()->assertJson(['status' => 'approved'])->json('token');
        $this->assertStringStartsWith('wst_', $new);
        $this->assertSame('approved', Workstation::first()->status);
        $this->assertSame(AgentProtocol::hashToken($new), Workstation::first()->token_hash);
        // key sent as Bearer works too; the old token is now dead
        $this->postJson('/api/agent/checkin', ['workstation_id' => 'ws-001'], ['Authorization' => 'Bearer boot-secret'])
            ->assertOk()->assertJsonPath('status', 'approved');
        $this->postJson('/api/agent/checkin', ['workstation_id' => 'ws-001'], ['Authorization' => "Bearer $old"])->assertStatus(401);
        // no token and a wrong key: rejected
        $this->postJson('/api/agent/checkin', ['workstation_id' => 'ws-001'], ['X-API-Key' => 'nope'])->assertStatus(401);
    }

    public function test_blocked_workstation_gets_403(): void
    {
        [, $token] = $this->approvedWithToken('ws-001', ['status' => 'blocked']);
        $this->postJson('/api/agent/checkin', ['workstation_id' => 'ws-001'], ['Authorization' => "Bearer $token"])->assertStatus(403);
    }

    public function test_checkin_sends_commands_marks_sent_and_records_acks_and_reports(): void
    {
        [, $token] = $this->approvedWithToken('ws-001');
        $c = Command::create(['workstation_id' => 'ws-001', 'command' => 'restart']);
        $bad = Command::create(['workstation_id' => 'ws-001', 'command' => 'rm -rf /']);   // injected directly into DB

        $res = $this->postJson('/api/agent/checkin', ['workstation_id' => 'ws-001'], ['Authorization' => "Bearer $token"])->assertOk();
        $this->assertSame([['id' => $c->id, 'command' => 'restart']], $res->json('commands'));
        $this->assertSame('sent', $c->fresh()->status);
        $this->assertSame('pending', $bad->fresh()->status);

        $this->postJson('/api/agent/checkin', ['workstation_id' => 'ws-001',
            'command_results' => [['id' => $c->id, 'status' => 'ok', 'result' => 'restarted']],
            'reports' => [['type' => 'update_installed', 'version' => '1.1.0'], ['type' => 'evil', 'message' => 'x']]],
            ['Authorization' => "Bearer $token"])->assertOk();
        $this->assertSame('done', $c->fresh()->status);
        $this->assertTrue(Event::where('type', 'update_installed')->where('version', '1.1.0')->exists());
        $this->assertFalse(Event::where('type', 'evil')->exists());
    }

    public function test_env_is_merged_and_locked_keys_never_sent(): void
    {
        [, $token] = $this->approvedWithToken('ws-001', ['warehouse_code' => 'WH1']);
        \App\Workstations\Models\EnvEntry::insert([
            ['scope' => 'global', 'scope_ref' => '', 'key' => 'POLL_INTERVAL', 'value' => '30'],
            ['scope' => 'global', 'scope_ref' => '', 'key' => 'CO2_SCALE', 'value' => '1'],
            ['scope' => 'warehouse', 'scope_ref' => 'WH1', 'key' => 'POLL_INTERVAL', 'value' => '20'],
            ['scope' => 'workstation', 'scope_ref' => 'ws-001', 'key' => 'CO2_SCALE', 'value' => '2'],
            ['scope' => 'global', 'scope_ref' => '', 'key' => 'DB_URL', 'value' => 'mysql://evil'],
        ]);
        $res = $this->postJson('/api/agent/checkin', ['workstation_id' => 'ws-001'], ['Authorization' => "Bearer $token"])->assertOk();
        $this->assertSame(['CO2_SCALE' => '2', 'POLL_INTERVAL' => '20'], $res->json('env.values'));
        $hash = $res->json('env.hash');
        $this->postJson('/api/agent/checkin', ['workstation_id' => 'ws-001', 'env_hash' => $hash], ['Authorization' => "Bearer $token"])
            ->assertOk()->assertJsonMissingPath('env');
    }

    // ── update decision & download ──────────────────────────────────────

    public function test_update_offered_for_latest_signed_release(): void
    {
        [, $token] = $this->approvedWithToken('ws-001');
        $rel = $this->release('1.1.0', ['is_latest' => true]);
        $this->postJson('/api/agent/checkin', ['workstation_id' => 'ws-001', 'version' => '1.0.0'], ['Authorization' => "Bearer $token"])
            ->assertOk()->assertJsonPath('update.id', $rel->id)->assertJsonPath('update.sha256', $rel->sha256)
            ->assertJsonPath('update.signature', $rel->signature)->assertJsonPath('update.type', 'binary');
    }

    public function test_unsigned_or_badly_signed_release_is_never_offered(): void
    {
        [, $token] = $this->approvedWithToken('ws-001');
        $this->release('1.1.0', ['is_latest' => true, 'signature' => base64_encode(str_repeat('x', 64))]);
        $this->postJson('/api/agent/checkin', ['workstation_id' => 'ws-001', 'version' => '1.0.0'], ['Authorization' => "Bearer $token"])
            ->assertOk()->assertJsonMissingPath('update');
    }

    public function test_download_requires_approved_token(): void
    {
        $rel = $this->release('1.1.0', ['is_latest' => true]);
        [, $token] = $this->approvedWithToken('ws-001');
        $this->workstation('ws-p', ['token_hash' => AgentProtocol::hashToken('wst_pending')]);

        $this->get("/api/agent/releases/{$rel->id}/download")->assertStatus(403);
        $this->get("/api/agent/releases/{$rel->id}/download", ['Authorization' => 'Bearer wst_pending'])->assertStatus(403);
        $this->get("/api/agent/releases/{$rel->id}/download", ['Authorization' => "Bearer $token"])
            ->assertOk()->assertHeader('X-Sha256', $rel->sha256);
    }

    public function test_download_of_yanked_release_is_forbidden(): void
    {
        $rel = $this->release('1.1.0', ['yanked' => true]);
        [, $token] = $this->approvedWithToken('ws-001');
        $this->get("/api/agent/releases/{$rel->id}/download", ['Authorization' => "Bearer $token"])->assertStatus(403);
    }

    public function test_checkin_is_rate_limited(): void
    {
        config(['workstations.checkin_per_minute' => 3]);
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/agent/checkin', ['workstation_id' => 'ws-x'], ['X-API-Key' => 'boot-secret'])->assertOk();
        }
        $this->postJson('/api/agent/checkin', ['workstation_id' => 'ws-x'], ['X-API-Key' => 'boot-secret'])->assertStatus(429);
    }
}
