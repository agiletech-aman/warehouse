<?php

namespace App\Workstations\Support;

/** Turns raw event/command rows into plain-language sentences for the admin. */
class Timeline
{
    public static function commandName(string $c): string
    {
        return ['restart' => 'Restart', 'sync_now' => 'Sync now', 'stop_polling' => 'Polling band karo'][$c] ?? $c;
    }

    public static function event(string $type, ?string $version, ?string $message): string
    {
        $v = $version ? "v$version" : 'naya version';
        $text = match ($type) {
            'registered' => 'Workstation pehli baar juda — approval ka intezaar',
            'token_issued' => 'Workstation ko login token mil gaya',
            'version_changed' => "Version badla: $message",
            'update_installed' => "$v install ho gaya",
            'update_ok' => "$v theek chal raha hai",
            'update_failed' => "$v install nahi ho paya",
            'rollback' => "$v mein dikkat thi — purane version par wapas gaya",
            'env_applied' => 'Nayi settings lag gayi',
            'env_error' => 'Kuch settings lag nahi payi',
            'admin_approve' => 'Admin ne approve kiya',
            'admin_block' => 'Admin ne block kiya',
            'admin_reset_token' => 'Admin ne token reset kiya',
            'admin_save' => 'Admin ne details update ki',
            'admin_command' => 'Admin ne "' . self::commandName((string)$message) . '" bheja',
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
            'pending' => 'queue mein hai, agle check-in par jayega',
            'sent' => 'workstation ko bhej diya',
            'done' => 'ho gaya',
            'failed' => 'fail ho gaya',
            default => $status,
        };
        return "\"$name\" $s" . ($result ? " — $result" : '');
    }

    /** "5 min pehle" style. */
    public static function pehle($t): string
    {
        if (!$t) {
            return 'kabhi nahi';
        }
        $s = (int)$t->diffInSeconds(now(), true);
        return match (true) {
            $s < 60 => 'abhi abhi',
            $s < 3600 => intdiv($s, 60) . ' min pehle',
            $s < 86400 => intdiv($s, 3600) . ' ghante pehle',
            default => intdiv($s, 86400) . ' din pehle',
        };
    }
}
