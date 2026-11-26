<?php

namespace Tests\Feature\Workstations;

use App\Workstations\Models\{Release, Workstation};
use App\Workstations\Support\AgentProtocol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

abstract class WorkstationTestCase extends TestCase
{
    use RefreshDatabase;

    protected string $secretKey;
    protected string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();
        $pair = sodium_crypto_sign_keypair();
        $this->secretKey = sodium_crypto_sign_secretkey($pair);
        $this->publicKey = base64_encode(sodium_crypto_sign_publickey($pair));
        config([
            'workstations.public_key' => $this->publicKey,
            'workstations.bootstrap_key' => 'boot-secret',
            'workstations.admin_user' => 'admin',
            'workstations.admin_pass' => 'pass123',
            'workstations.require_https' => false,
            'workstations.allowed_ips' => [],
            'workstations.disk' => 'local',
        ]);
        Storage::fake('local');
    }

    protected function sign(string $version, string $sha): string
    {
        return base64_encode(sodium_crypto_sign_detached(AgentProtocol::signedMessage($version, $sha), $this->secretKey));
    }

    protected function release(string $version, array $attrs = []): Release
    {
        $sha = hash('sha256', "bin-$version");
        Storage::disk('local')->put("workstations/releases/$version", "bin-$version");
        return Release::create($attrs + ['version' => $version, 'file_path' => "workstations/releases/$version",
                'sha256' => $sha, 'signature' => $this->sign($version, $sha), 'size' => 7, 'type' => 'binary']);
    }

    protected function workstation(string $id, array $attrs = []): Workstation
    {
        return Workstation::create($attrs + ['workstation_id' => $id, 'status' => 'pending', 'auto_update' => true]);
    }

    protected function approvedWithToken(string $id, array $attrs = []): array
    {
        $token = 'wst_test_' . $id;
        $w = $this->workstation($id, $attrs + ['status' => 'approved', 'token_hash' => AgentProtocol::hashToken($token)]);
        return [$w, $token];
    }

    protected function admin(): static
    {
        return $this->withSession(['wsm_admin' => true]);
    }
}
