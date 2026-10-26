<?php

namespace App\Fleet\Support;

/**
 * Framework-free decision logic for the fleet check-in protocol.
 *
 * Controllers only load rows into arrays, call decide(), then persist what it
 * returns. Keeping this pure means it can be tested with plain `php` and is
 * shared by the standalone test server (tools/mock_fleet_server.php).
 */
class FleetCore
{
    const ALLOWED_COMMANDS = ['restart', 'start_polling', 'stop_polling', 'sync_now', 'fetch_now', 'clear_bad_versions'];
    const REPORT_TYPES = ['update_installed', 'update_failed', 'update_ok', 'rollback', 'env_applied', 'env_error'];

    /** Keys the cloud must never write into a workstation's .env (mirrors the agent's ENV_DENY). */
    const ENV_DENY = ['DB_URL', 'CENTRAL_API', 'CENTRAL_ENABLED', 'CENTRAL_API_KEY', 'WORKSTATION_ID',
        'WAREHOUSE_CODE', 'UPDATE_PUBLIC_KEY', 'AUTO_UPDATE'];
    const ENV_INT = ['POLL_INTERVAL', 'MODBUS_TIMEOUT', 'OFFLINE_THRESHOLD', 'API_PORT', 'CLOUD_SYNC_TIMEOUT',
        'CLOUD_SYNC_BATCH_LIMIT', 'READINGS_LIMIT_DEFAULT', 'CLOUD_NORMAL_SYNC_INTERVAL',
        'CLOUD_ALERT_SYNC_INTERVAL', 'READINGS_RETAIN_DAYS', 'MODBUS_PARALLEL_WORKERS', 'MODBUS_STAGGER_MS',
        'CENTRAL_CHECK_INTERVAL'];
    const ENV_FLOAT = ['CO2_NORMAL_MAX', 'CO2_WARN_MAX', 'CO2_SEVERE_MAX', 'CO2_CRITICAL_MAX', 'PH3_NORMAL_MAX',
        'PH3_WARN_MAX', 'PH3_SEVERE_MAX', 'PH3_CRITICAL_MAX', 'DEMO_CO2_MIN', 'DEMO_CO2_MAX', 'CO2_SCALE', 'PH3_SCALE'];

    /** Returns an error string, or null when the key/value is acceptable. */
    public static function validateEnvEntry(string $key, string $value): ?string
    {
        if (!preg_match('/^[A-Z][A-Z0-9_]{0,63}$/', $key)) {
            return 'Key must be UPPER_SNAKE_CASE (A-Z, 0-9, _), max 64 chars.';
        }
        if (in_array($key, self::ENV_DENY, true)) {
            return "$key is local-only and cannot be managed from the cloud.";
        }
        if (strpos($value, "\n") !== false || strpos($value, "\r") !== false) {
            return 'Value must be a single line.';
        }
        if (in_array($key, self::ENV_INT, true) && !preg_match('/^-?\d+$/', trim($value))) {
            return "$key must be a whole number.";
        }
        if (in_array($key, self::ENV_FLOAT, true) && !is_numeric(trim($value))) {
            return "$key must be a number.";
        }
        return null;
    }

    /** global < warehouse < workstation. All values returned as strings. */
    public static function effectiveEnv(array $global, array $warehouse, array $workstation): array
    {
        $out = [];
        foreach ([$global, $warehouse, $workstation] as $layer) {
            foreach ($layer as $k => $v) {
                $out[(string)$k] = (string)$v;
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

    /** The agent's applied hash may carry a "!err" suffix when it rejected some keys. */
    public static function envInSync(?string $applied, array $env): bool
    {
        return $applied !== null && $applied === self::envHash($env);
    }

    public static function newToken(): string
    {
        return 'gmt_' . bin2hex(random_bytes(24));
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Message that is Ed25519-signed offline; binds version AND content. */
    public static function signedMessage(string $version, string $sha256): string
    {
        return 'gasmonitor|' . $version . '|' . strtolower($sha256);
    }

    /** True = valid. Requires ext-sodium (bundled with PHP >= 7.2). */
    public static function verifySignature(string $publicKeyB64, string $version, string $sha256, string $sigB64): bool
    {
        $pub = base64_decode($publicKeyB64, true);
        $sig = base64_decode($sigB64, true);
        if ($pub === false || $sig === false || strlen($pub) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }
        return sodium_crypto_sign_verify_detached($sig, self::signedMessage($version, $sha256), $pub);
    }

    /**
     * One check-in.
     *
     * @param array|null $ws  existing workstation row (columns as array) or null if unknown
     * @param array      $in  JSON body sent by the agent
     * @param array      $ctx now, request_ip, bootstrap_ok(bool), bearer(?string), env(array effective),
     *                        latest(?array release), target(?array pinned release), commands(array of
     *                        {id,command} still pending for this ws), interval(int)
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

        // ── authentication ────────────────────────────────────────────────
        if ($ws !== null && ($ws['status'] ?? '') === 'blocked') {
            return self::fail($res, 403, 'workstation blocked');
        }
        if ($ws !== null && !empty($ws['token_hash'])) {
            $bearer = $ctx['bearer'] ?? null;
            if (!$bearer || !hash_equals($ws['token_hash'], self::hashToken($bearer))) {
                return self::fail($res, 401, 'token required or invalid');
            }
        } elseif (empty($ctx['bootstrap_ok'])) {
            return self::fail($res, 401, 'invalid api key');
        }

        // ── register / telemetry ──────────────────────────────────────────
        $created = false;
        if ($ws === null) {
            $created = true;
            $ws = ['workstation_id' => $wid, 'status' => 'pending', 'auto_update' => 1, 'token_hash' => null,
                'warehouse_code' => null, 'label' => null, 'target_release_id' => null, 'created_at' => $now,
                'current_version' => null];
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
        $ws['devices_total'] = (int)($in['devices_total'] ?? 0);
        $ws['devices_online'] = (int)($in['devices_online'] ?? 0);
        $ws['alerts'] = (int)($in['alerts'] ?? 0);
        $ws['polling_running'] = !empty($in['polling_running']) ? 1 : 0;
        $ws['demo_mode'] = !empty($in['demo_mode']) ? 1 : 0;
        $ws['uptime_s'] = (int)($in['uptime_s'] ?? 0);
        $ws['env_applied_hash'] = self::str($in['env_hash'] ?? null, 80);
        $ws['update_state'] = self::str($in['update_state'] ?? 'idle', 32) ?: 'idle';
        $ws['update_message'] = self::str($in['update_message'] ?? null, 500);
        $ws['bad_versions'] = json_encode(array_values(array_filter((array)($in['bad_versions'] ?? []), 'is_string')));
        $ws['reported_warehouse_codes'] = json_encode(array_values(array_filter((array)($in['warehouse_codes'] ?? []), 'is_string')));
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

        // ── not approved yet: telemetry only, no token, no payloads ──────
        if (($ws['status'] ?? 'pending') !== 'approved') {
            $res['body'] = ['status' => 'pending', 'check_interval' => min($interval, 120)];
            return $res;
        }

        // ── approved ──────────────────────────────────────────────────────
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

        if (!empty($ws['auto_update'])) {
            $rel = $ctx['target'] ?? $ctx['latest'] ?? null;
            $bad = array_filter((array)($in['bad_versions'] ?? []), 'is_string');
            if ($rel && $rel['version'] !== $ver && !in_array($rel['version'], $bad, true)) {
                $body['update'] = ['id' => (int)$rel['id'], 'version' => $rel['version'], 'sha256' => $rel['sha256'],
                    'signature' => $rel['signature'] ?? null, 'size' => (int)$rel['size']];
            }
        }

        $res['body'] = $body;
        return $res;
    }

    /** Dashboard helper: is this workstation on the version it SHOULD run? */
    public static function versionState(?string $current, ?array $latest, ?array $target, bool $autoUpdate): string
    {
        if (!$current) {
            return 'unknown';
        }
        $want = $autoUpdate ? ($target['version'] ?? $latest['version'] ?? null) : null;
        if ($want === null) {
            return $latest && $latest['version'] === $current ? 'latest' : ($autoUpdate ? 'unknown' : 'frozen');
        }
        return $want === $current ? 'latest' : 'outdated';
    }

    private static function fail(array $res, int $http, string $msg): array
    {
        $res['http'] = $http;
        $res['body'] = ['error' => $msg];
        $res['ws'] = null;
        $res['events'] = [];
        $res['acks'] = [];
        return $res;
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
