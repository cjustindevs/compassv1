<?php

return [
    'enabled' => env('VOICE_CALLS_ENABLED', false),
    'metered_domain' => env('METERED_DOMAIN'),
    'metered_secret' => env('METERED_SECRET_KEY'),
    'ws_host' => env('VOICE_WS_HOST'),
    'ws_port' => (int) env('VOICE_WS_PORT', 443),
    'ws_scheme' => env('VOICE_WS_SCHEME', 'https'),
    'ring_seconds' => 45,
    'connection_seconds' => 60,
    'heartbeat_seconds' => 45,
];
