<?php

return [
    // Same value the workstations already send as CLOUD_API_KEY (X-API-Key) — used only for first registration.
    'bootstrap_key' => env('FLEET_BOOTSTRAP_KEY', env('CLOUD_API_KEY', '')),
    'check_interval' => (int)env('FLEET_CHECK_INTERVAL', 300),
    // Basic-auth login for the dashboard. Replace with your own auth middleware if the portal has one.
    'admin_user' => env('FLEET_ADMIN_USER', 'admin'),
    'admin_pass' => env('FLEET_ADMIN_PASS', ''),
    'disk' => env('FLEET_DISK', 'local'),
    'public_key' => env('FLEET_PUBLIC_KEY', ''),   // Ed25519 pub (base64) — embedded into installer
    'offline_after' => (int)env('FLEET_OFFLINE_AFTER', 900),
];
