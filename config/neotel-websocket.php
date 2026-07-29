<?php

return [
    'enabled' => false,
    'websocket_url' => '',
    'user' => '',
    'password' => '',
    'verify_ssl' => true,
    'read_timeout' => 30,
    'initial_backoff_seconds' => 1,
    'max_backoff_seconds' => 30,
    'user_agent' => 'vendor-neotel-websocket/0.1',
    'log_channel' => null,
    'events_enabled' => true,
    'db_enabled' => true,
    'register_command' => true,
    'load_migrations' => true,
    'api_prefix' => 'api/neotel-websocket',
];
