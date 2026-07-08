<?php

return [
    'enabled' => (bool) env('NEOTEL_ENABLED', false),
    'websocket_url' => env('NEOTEL_WEBSOCKET_URL', ''),
    'user' => env('NEOTEL_USER', ''),
    'password' => env('NEOTEL_PASSWORD', ''),
    'verify_ssl' => (bool) env('NEOTEL_VERIFY_SSL', true),
    'read_timeout' => max(1, (int) env('NEOTEL_READ_TIMEOUT', 30)),
    'initial_backoff_seconds' => max(1, (int) env('NEOTEL_INITIAL_BACKOFF_SECONDS', 1)),
    'max_backoff_seconds' => max(1, (int) env('NEOTEL_MAX_BACKOFF_SECONDS', 30)),
    'user_agent' => env('NEOTEL_USER_AGENT', 'vendor-neotel-websocket/0.1'),
    'log_channel' => env('NEOTEL_LOG_CHANNEL', null),
    'record_call_events' => (bool) env('NEOTEL_RECORD_CALL_EVENTS', true),
];
