<?php

return [
    // Shared enrollment key. Sent by agents as "X-API-Key" or "Authorization: Bearer" — never in a URL.
    // Prefer one-time install tokens from the "Add Workstation" wizard instead of handing this out.
    'bootstrap_key' => env('WSM_BOOTSTRAP_KEY', ''),

    // Ed25519 public key (base64, 32 raw bytes). REQUIRED: without it no release can be uploaded.
    'public_key' => env('WSM_PUBLIC_KEY', ''),

    // Admin portal login.
    'admin_user' => env('WSM_ADMIN_USER', 'admin'),
    'admin_pass' => env('WSM_ADMIN_PASS', ''),

    // Portal hardening.
    'require_https' => (bool)env('WSM_REQUIRE_HTTPS', env('APP_ENV') === 'production'),
    'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string)env('WSM_ALLOWED_IPS', ''))))),

    // Agent behaviour.
    'check_interval' => (int)env('WSM_CHECK_INTERVAL', 300),
    'offline_after' => (int)env('WSM_OFFLINE_AFTER', 900),
    'install_token_ttl' => (int)env('WSM_INSTALL_TOKEN_TTL', 1440),   // minutes
    'checkin_per_minute' => (int)env('WSM_CHECKIN_PER_MINUTE', 30),
    'bootstrap_per_minute' => (int)env('WSM_BOOTSTRAP_PER_MINUTE', 10),

    'disk' => env('WSM_DISK', 'local'),
    'service_name' => 'gasmonitor',

    // Fixed post-update actions for "package" releases. Admins pick a NAME; the agent receives the
    // argv lists below. Nothing typed by a user is ever executed.
    'post_update_actions' => [
        'restart_service' => [
            ['systemctl', 'restart', 'gasmonitor'],
        ],
        'composer_install_restart' => [
            ['composer', 'install', '--no-dev', '--no-interaction', '--optimize-autoloader'],
            ['php', 'artisan', 'migrate', '--force'],
            ['systemctl', 'restart', 'gasmonitor'],
        ],
    ],
];
