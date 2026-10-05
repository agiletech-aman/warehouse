<?php

return [
    'push_secret' => env('FNS_PUSH_SECRET'),

    // Older detections (before the first row in fns_detections) are read from this API.
    'history' => [
        'enabled' => env('FNS_HISTORY_ENABLED', true),
        'url' => env('FNS_HISTORY_URL', 'http://216.48.182.224:8003/api/camera-alerts'),
        'alert_types' => array_filter(array_map('trim', explode(',', env('FNS_HISTORY_ALERT_TYPES', 'fire,rodent')))),
        'cache_store' => env('FNS_HISTORY_CACHE_STORE', 'file'),
        'cache_minutes' => (int) env('FNS_HISTORY_CACHE_MINUTES', 30),
    ],
];
