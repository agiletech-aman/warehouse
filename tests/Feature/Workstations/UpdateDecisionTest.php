<?php

namespace Tests\Feature\Workstations;

use App\Workstations\Support\AgentProtocol;

/** Pure decision logic: which release a workstation should get. */
class UpdateDecisionTest extends WorkstationTestCase
{
    private function rel(int $id, string $v, array $extra = []): array
    {
        $sha = hash('sha256', $v);
        return $extra + ['id' => $id, 'version' => $v, 'sha256' => $sha, 'signature' => $this->sign($v, $sha),
                'size' => 1, 'type' => 'binary', 'yanked' => false, 'post_update' => null];
    }

    private function decide(array $ws, ?string $current, array $ctx, array $bad = []): ?array
    {
        return AgentProtocol::updateFor($ws, $current, $bad, $ctx + ['public_key' => $this->publicKey,
                'post_update_actions' => config('workstations.post_update_actions')]);
    }

    public function test_latest_is_offered_when_auto_update_on(): void
    {
        $u = $this->decide(['auto_update' => 1], '1.0.0', ['latest' => $this->rel(2, '1.1.0')]);
        $this->assertSame('1.1.0', $u['version']);
    }

    public function test_nothing_when_already_on_latest(): void
    {
        $this->assertNull($this->decide(['auto_update' => 1], '1.1.0', ['latest' => $this->rel(2, '1.1.0')]));
    }

    public function test_pinned_release_wins_over_latest_even_for_downgrade(): void
    {
        $u = $this->decide(['auto_update' => 1], '1.1.0', ['latest' => $this->rel(2, '1.1.0'), 'target' => $this->rel(1, '1.0.0')]);
        $this->assertSame('1.0.0', $u['version']);
    }

    public function test_auto_update_off_gets_nothing_even_if_pinned(): void
    {
        $this->assertNull($this->decide(['auto_update' => 0], '1.0.0', ['latest' => $this->rel(2, '1.1.0'), 'target' => $this->rel(3, '1.2.0')]));
    }

    public function test_bad_versions_reported_by_agent_are_skipped(): void
    {
        $this->assertNull($this->decide(['auto_update' => 1], '1.0.0', ['latest' => $this->rel(2, '1.1.0')], ['1.1.0']));
    }

    public function test_unsigned_release_is_not_offered(): void
    {
        $this->assertNull($this->decide(['auto_update' => 1], '1.0.0', ['latest' => $this->rel(2, '1.1.0', ['signature' => null])]));
    }

    public function test_signature_for_other_version_is_rejected(): void
    {
        $r = $this->rel(2, '1.1.0');
        $r['signature'] = $this->sign('9.9.9', $r['sha256']);
        $this->assertNull($this->decide(['auto_update' => 1], '1.0.0', ['latest' => $r]));
    }

    public function test_package_release_carries_whitelisted_post_update_steps(): void
    {
        $u = $this->decide(['auto_update' => 1], '1.0.0',
            ['latest' => $this->rel(2, '1.1.0', ['type' => 'package', 'post_update' => 'composer_install_restart'])]);
        $this->assertSame('package', $u['type']);
        $this->assertSame('composer_install_restart', $u['post_update']['name']);
        $this->assertSame(config('workstations.post_update_actions.composer_install_restart'), $u['post_update']['steps']);

        $u = $this->decide(['auto_update' => 1], '1.0.0',
            ['latest' => $this->rel(2, '1.1.0', ['type' => 'package', 'post_update' => 'not_in_config'])]);
        $this->assertArrayNotHasKey('post_update', $u);
    }

    public function test_version_state_helper(): void
    {
        $latest = ['version' => '1.1.0'];
        $this->assertSame('latest', AgentProtocol::versionState('1.1.0', $latest, null, true));
        $this->assertSame('outdated', AgentProtocol::versionState('1.0.0', $latest, null, true));
        $this->assertSame('frozen', AgentProtocol::versionState('1.0.0', $latest, null, false));
        $this->assertSame('latest', AgentProtocol::versionState('1.0.0', $latest, ['version' => '1.0.0'], true));
    }

    public function test_env_validation(): void
    {
        $this->assertNull(AgentProtocol::validateEnvEntry('POLL_INTERVAL', '30'));
        $this->assertNotNull(AgentProtocol::validateEnvEntry('POLL_INTERVAL', 'abc'));
        $this->assertNotNull(AgentProtocol::validateEnvEntry('CO2_SCALE', '1.x'));
        $this->assertNotNull(AgentProtocol::validateEnvEntry('DB_URL', 'x'));
        $this->assertNotNull(AgentProtocol::validateEnvEntry('lower', 'x'));
        $this->assertNotNull(AgentProtocol::validateEnvEntry('FOO', "a\nb"));
    }
}
