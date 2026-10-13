<?php

return [
    'push_secret' => env('FNS_PUSH_SECRET'),

    // Detections older than the first row in fns_detections / fns_detections_02 are read from this API.
    'history' => [
        'enabled' => env('FNS_HISTORY_ENABLED', true),
        'url' => env('FNS_HISTORY_URL', 'http://216.48.182.224:8003/api/camera-alerts'),
        // Comma separated alert types to fetch; empty fetches every type.
        'alert_types' => array_filter(array_map('trim', explode(',', env('FNS_HISTORY_ALERT_TYPES', '')))),
        'cache_store' => env('FNS_HISTORY_CACHE_STORE', 'file'),
        'cache_minutes' => (int) env('FNS_HISTORY_CACHE_MINUTES', 30),
    ],
];
