<?php

namespace App\Workstations\Support;

/**
 * Framework-free decision logic for the agent check-in protocol.
 *
 * Controllers load rows into arrays, call decide(), then persist what it returns.
 * Nothing here touches the database, so every rule can be unit/feature tested directly.
 */
class AgentProtocol
{
    /** The ONLY commands a workstation can be told to run. */
    const ALLOWED_COMMANDS = ['restart', 'sync_now', 'stop_polling'];

    const REPORT_TYPES = ['update_installed', 'update_failed', 'update_ok', 'rollback', 'env_applied', 'env_error'];

    const RELEASE_TYPES = ['binary', 'package'];

    /** Keys the cloud must never write into a workstation's .env. */
    const ENV_LOCKED = ['DB_URL', 'CENTRAL_API', 'CENTRAL_ENABLED', 'CENTRAL_API_KEY', 'CLOUD_API_KEY', 'WORKSTATION_ID',
        'WAREHOUSE_CODE', 'UPDATE_PUBLIC_KEY', 'AUTO_UPDATE'];
    const ENV_INT = ['POLL_INTERVAL', 'MODBUS_TIMEOUT', 'OFFLINE_THRESHOLD', 'API_PORT', 'CLOUD_SYNC_TIMEOUT',
        'CLOUD_SYNC_BATCH_LIMIT', 'READINGS_LIMIT_DEFAULT', 'CLOUD_NORMAL_SYNC_INTERVAL',
        'CLOUD_ALERT_SYNC_INTERVAL', 'READINGS_RETAIN_DAYS', 'MODBUS_PARALLEL_WORKERS', 'MODBUS_STAGGER_MS',
        'CENTRAL_CHECK_INTERVAL'];
    const ENV_FLOAT = ['CO2_NORMAL_MAX', 'CO2_WARN_MAX', 'CO2_SEVERE_MAX', 'CO2_CRITICAL_MAX', 'PH3_NORMAL_MAX',
        'PH3_WARN_MAX', 'PH3_SEVERE_MAX', 'PH3_CRITICAL_MAX', 'DEMO_CO2_MIN', 'DEMO_CO2_MAX', 'CO2_SCALE', 'PH3_SCALE'];

    // ── env ──────────────────────────────────────────────────────────────

    /** Returns a human-readable error, or null when the key/value is acceptable. */
    public static function validateEnvEntry(string $key, string $value): ?string
    {
        if (!preg_match('/^[A-Z][A-Z0-9_]{0,63}$/', $key)) {
            return 'Key sirf CAPITAL letters, numbers aur _ mein ho (jaise POLL_INTERVAL), max 64 characters.';
        }
        if (in_array($key, self::ENV_LOCKED, true)) {
            return "$key locked hai — ye sirf workstation par hi set hoti hai.";
        }
        if (str_contains($value, "\n") || str_contains($value, "\r")) {
            return 'Value ek hi line mein honi chahiye.';
        }
        if (in_array($key, self::ENV_INT, true) && !preg_match('/^-?\d+$/', trim($value))) {
            return "$key ki value poora number honi chahiye (jaise 30).";
        }
        if (in_array($key, self::ENV_FLOAT, true) && !is_numeric(trim($value))) {
            return "$key ki value number honi chahiye (jaise 2.5).";
        }
        return null;
    }

    /** global < warehouse < workstation. All values returned as strings, sorted by key. */
    public static function effectiveEnv(array $global, array $warehouse, array $workstation): array
    {
        $out = [];
        foreach ([$global, $warehouse, $workstation] as $layer) {
            foreach ($layer as $k => $v) {
                if (!in_array((string)$k, self::ENV_LOCKED, true)) {
                    $out[(string)$k] = (string)$v;
                }
            }
        }
        ksort($out);
        return $out;
    }

    public static function envHash(array $env): string
    {
        ksort($env);
        return hash('sha256', json_encode((object)$env, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public static function envInSync(?string $applied, array $env): bool
    {
        return $applied !== null && $applied === self::envHash($env);
    }

    // ── tokens ───────────────────────────────────────────────────────────

    public static function newToken(string $prefix = 'wst_'): string
    {
        return $prefix . bin2hex(random_bytes(24));
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    // ── signatures ───────────────────────────────────────────────────────

    /** Message that is Ed25519-signed offline; binds version AND content. */
    public static function signedMessage(string $version, string $sha256): string
    {
        return 'gasmonitor|' . $version . '|' . strtolower($sha256);
    }

    public static function validPublicKey(?string $b64): bool
    {
        $raw = base64_decode((string)$b64, true);
        return $raw !== false && strlen($raw) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES;
    }

    public static function verifySignature(?string $publicKeyB64, string $version, string $sha256, ?string $sigB64): bool
    {
        if (!self::validPublicKey($publicKeyB64) || !$sigB64) {
            return false;
        }
        $sig = base64_decode($sigB64, true);
        if ($sig === false || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }
        return sodium_crypto_sign_verify_detached($sig, self::signedMessage($version, $sha256), base64_decode($publicKeyB64));
    }

    // ── check-in ─────────────────────────────────────────────────────────

    /**
     * One check-in.
     *
     * @param array|null $ws  existing workstation row or null if unknown
     * @param array      $in  JSON body sent by the agent
     * @param array      $ctx now, request_ip, enroll_ok(bool), bearer(?string), env(array effective),
     *                        latest(?array release), target(?array pinned release), commands(array of
     *                        {id,command} pending), interval(int), public_key(string), post_update_actions(array)
     * @return array {http, body, ws, events[], acks[], send_command_ids[], created}
     */
    public static function decide(?array $ws, array $in, array $ctx): array
    {
        $now = $ctx['now'];
        $res = ['http' => 200, 'body' => [], 'ws' => $ws, 'events' => [], 'acks' => [],
            'send_command_ids' => [], 'created' => false];

        $wid = (string)($in['workstation_id'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $wid)) {
            return self::fail($res, 422, 'invalid workstation_id');
        }

        // ── authentication ────────────────────────────────────────────
        if ($ws !== null && ($ws['status'] ?? '') === 'blocked') {
            return self::fail($res, 403, 'workstation blocked');
        }
        if ($ws !== null && !empty($ws['token_hash'])) {
            $bearer = $ctx['bearer'] ?? null;
            if (!$bearer || !hash_equals($ws['token_hash'], self::hashToken($bearer))) {
                return self::fail($res, 401, 'token required or invalid');
            }
        } elseif (empty($ctx['enroll_ok'])) {
            return self::fail($res, 401, 'invalid enrollment key');
        }

        // ── register / telemetry ──────────────────────────────────────
        $created = false;
        if ($ws === null) {
            $created = true;
            $ws = ['workstation_id' => $wid, 'status' => 'pending', 'auto_update' => 1, 'token_hash' => null,
                'warehouse_code' => $ctx['enroll_warehouse'] ?? null, 'label' => null, 'target_release_id' => null,
                'created_at' => $now, 'current_version' => null];
            $res['events'][] = ['type' => 'registered', 'version' => null, 'created_at' => $now,
                'message' => 'first check-in, waiting for approval'];
        }
        $prevVersion = $ws['current_version'] ?? null;
        $ver = self::str($in['version'] ?? null, 32);
        if (!$created && $ver && $prevVersion && $ver !== $prevVersion) {
            $res['events'][] = ['type' => 'version_changed', 'version' => $ver, 'created_at' => $now,
                'message' => "$prevVersion -> $ver"];
        }
        $reportedWh = self::str($in['warehouse_code'] ?? null, 64);
        $ws['current_version'] = $ver;
        $ws['hostname'] = self::str($in['hostname'] ?? null, 128);
        $ws['os'] = self::str($in['os'] ?? null, 190);
        $ws['ip'] = self::str($in['ip'] ?? null, 64);
        $ws['last_ip'] = self::str($ctx['request_ip'] ?? null, 64);
        $ws['frozen'] = !empty($in['frozen']) ? 1 : 0;
        $ws['launcher'] = !empty($in['launcher']) ? 1 : 0;
        $ws['devices_total'] = max(0, (int)($in['devices_total'] ?? 0));
        $ws['devices_online'] = max(0, (int)($in['devices_online'] ?? 0));
        $ws['alerts'] = max(0, (int)($in['alerts'] ?? 0));
        $ws['polling_running'] = !empty($in['polling_running']) ? 1 : 0;
        $ws['demo_mode'] = !empty($in['demo_mode']) ? 1 : 0;
        $ws['uptime_s'] = max(0, (int)($in['uptime_s'] ?? 0));
        $ws['env_applied_hash'] = self::str($in['env_hash'] ?? null, 80);
        $ws['update_state'] = self::str($in['update_state'] ?? 'idle', 32) ?: 'idle';
        $ws['update_message'] = self::str($in['update_message'] ?? null, 500);
        $ws['bad_versions'] = json_encode(self::strings($in['bad_versions'] ?? []));
        $ws['reported_warehouse_codes'] = json_encode(self::strings($in['warehouse_codes'] ?? []));
        $ws['reported_warehouse_code'] = $reportedWh;
        if (empty($ws['warehouse_code']) && $reportedWh) {
            $ws['warehouse_code'] = $reportedWh;          // first claim; admin can change it later
        }
        $ws['last_seen_at'] = $now;

        foreach (array_slice((array)($in['reports'] ?? []), 0, 50) as $r) {
            if (is_array($r) && in_array($r['type'] ?? '', self::REPORT_TYPES, true)) {
                $res['events'][] = ['type' => $r['type'], 'version' => self::str($r['version'] ?? null, 32),
                    'message' => self::str($r['message'] ?? '', 500), 'created_at' => $now];
            }
        }
        foreach (array_slice((array)($in['command_results'] ?? []), 0, 50) as $r) {
            if (is_array($r) && isset($r['id'])) {
                $res['acks'][] = ['id' => (int)$r['id'], 'status' => ($r['status'] ?? '') === 'ok' ? 'done' : 'failed',
                    'result' => self::str($r['result'] ?? '', 500)];
            }
        }

        $res['ws'] = $ws;
        $res['created'] = $created;
        $interval = (int)($ctx['interval'] ?? 300);

        // ── not approved yet: telemetry only, no token, no payloads ───
        if (($ws['status'] ?? 'pending') !== 'approved') {
            $res['body'] = ['status' => 'pending', 'check_interval' => min($interval, 120)];
            return $res;
        }

        // ── approved ──────────────────────────────────────────────────
        $body = ['status' => 'approved', 'check_interval' => $interval];

        if (empty($ws['token_hash'])) {
            $plain = self::newToken();
            $res['ws']['token_hash'] = self::hashToken($plain);
            $res['events'][] = ['type' => 'token_issued', 'version' => $ver, 'created_at' => $now,
                'message' => 'workstation token issued'];
            $body['token'] = $plain;
        }

        $cmds = [];
        foreach ((array)($ctx['commands'] ?? []) as $c) {
            if (in_array($c['command'] ?? '', self::ALLOWED_COMMANDS, true)) {
                $cmds[] = ['id' => (int)$c['id'], 'command' => $c['command']];
                $res['send_command_ids'][] = (int)$c['id'];
            }
        }
        if ($cmds) {
            $body['commands'] = $cmds;
        }

        $env = (array)($ctx['env'] ?? []);
        $hash = self::envHash($env);
        if (($in['env_hash'] ?? null) !== $hash) {
            $body['env'] = ['hash' => $hash, 'values' => (object)$env];
        }

        if ($update = self::updateFor($ws, $ver, self::strings($in['bad_versions'] ?? []), $ctx)) {
            $body['update'] = $update;
        }

        $res['body'] = $body;
        return $res;
    }

    /** Which release (if any) this workstation should install now. Unsigned releases are never offered. */
    public static function updateFor(array $ws, ?string $current, array $badVersions, array $ctx): ?array
    {
        if (empty($ws['auto_update'])) {
            return null;
        }
        $rel = $ctx['target'] ?? $ctx['latest'] ?? null;
        if (!$rel || !empty($rel['yanked']) || $rel['version'] === $current || in_array($rel['version'], $badVersions, true)) {
            return null;
        }
        if (!self::verifySignature($ctx['public_key'] ?? null, $rel['version'], $rel['sha256'], $rel['signature'] ?? null)) {
            return null;
        }
        $type = in_array($rel['type'] ?? 'binary', self::RELEASE_TYPES, true) ? ($rel['type'] ?? 'binary') : 'binary';
        $u = ['id' => (int)$rel['id'], 'version' => $rel['version'], 'type' => $type, 'sha256' => $rel['sha256'],
            'signature' => $rel['signature'], 'size' => (int)$rel['size']];
        if ($type === 'package' && !empty($rel['post_update'])) {
            $actions = (array)($ctx['post_update_actions'] ?? []);
            if (isset($actions[$rel['post_update']])) {
                $u['post_update'] = ['name' => $rel['post_update'], 'steps' => $actions[$rel['post_update']]];
            }
        }
        return $u;
    }

    /** Dashboard helper: is this workstation on the version it SHOULD run? */
    public static function versionState(?string $current, ?array $latest, ?array $target, bool $autoUpdate): string
    {
        if (!$current) {
            return 'unknown';
        }
        $want = $target['version'] ?? $latest['version'] ?? null;
        if ($want === null) {
            return 'unknown';
        }
        if ($want === $current) {
            return 'latest';
        }
        return $autoUpdate ? 'outdated' : 'frozen';
    }

    private static function fail(array $res, int $http, string $msg): array
    {
        return ['http' => $http, 'body' => ['error' => $msg], 'ws' => null, 'events' => [], 'acks' => [],
            'send_command_ids' => [], 'created' => false];
    }

    private static function strings($v): array
    {
        return array_values(array_slice(array_filter((array)$v, fn($x) => is_string($x) && strlen($x) <= 64), 0, 50));
    }

    private static function str($v, int $max): ?string
    {
        if ($v === null || is_array($v) || is_object($v)) {
            return null;
        }
        $v = trim((string)$v);
        return $v === '' ? null : mb_substr($v, 0, $max);
    }
}
