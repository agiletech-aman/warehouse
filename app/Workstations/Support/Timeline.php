<?php

namespace App\Workstations\Support;

/** Turns raw event/command rows into plain-language sentences for the admin. */
class Timeline
{
    public static function commandName(string $c): string
    {
        return ['restart' => 'Restart', 'sync_now' => 'Sync now', 'stop_polling' => 'Stop polling'][$c] ?? $c;
    }

    public static function event(string $type, ?string $version, ?string $message): string
    {
        $v = $version ? "v$version" : 'New version';
        $text = match ($type) {
            'registered' => 'Workstation connected for the first time — waiting for approval',
            'token_issued' => 'Workstation received its access token',
            'version_changed' => "Version changed: $message",
            'update_installed' => "$v installed",
            'update_ok' => "$v is running fine",
            'update_failed' => "$v failed to install",
            'rollback' => "$v had a problem — rolled back to the previous version",
            'env_applied' => 'New settings applied',
            'env_error' => 'Some settings could not be applied',
            'admin_approve' => 'Approved by admin',
            'admin_block' => 'Blocked by admin',
            'admin_reset_token' => 'Token reset by admin',
            'admin_save' => 'Details updated by admin',
            'admin_command' => 'Admin sent "' . self::commandName((string)$message) . '"',
            default => $type,
        };
        if ($message && !in_array($type, ['version_changed', 'admin_command', 'registered', 'token_issued'], true)
            && !str_starts_with($type, 'admin_')) {
            $text .= ' — ' . $message;
        }
        return $text;
    }

    public static function tone(string $type): string
    {
        return match ($type) {
            'update_installed', 'update_ok', 'env_applied', 'admin_approve', 'token_issued' => 'good',
            'update_failed', 'rollback', 'env_error', 'admin_block' => 'bad',
            default => 'info',
        };
    }

    public static function command(string $cmd, string $status, ?string $result): string
    {
        $name = self::commandName($cmd);
        $s = match ($status) {
            'pending' => 'queued, will be sent on the next check-in',
            'sent' => 'sent to the workstation',
            'done' => 'completed',
            'failed' => 'failed',
            default => $status,
        };
        return "\"$name\" $s" . ($result ? " — $result" : '');
    }

    /** "5 min ago" style. */
    public static function ago($t): string
    {
        if (!$t) {
            return 'never';
        }
        $s = (int)$t->diffInSeconds(now(), true);
        return match (true) {
            $s < 60 => 'just now',
            $s < 3600 => intdiv($s, 60) . ' min ago',
            $s < 86400 => intdiv($s, 3600) . ' h ago',
            default => intdiv($s, 86400) . ' days ago',
        };
    }
}
