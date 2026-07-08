# neotel-websocket

Minimal Neotel websocket client package with transport abstraction.

## Current status

- Core listener and auth flow are implemented.
- Default websocket transport is implemented via `textalk/websocket`.
- Smoke tests cover handshake flow and reconnect behavior with fake transport classes.
- Laravel service provider, publishable config, and `neotel:listen` command are included.
- Call events are persisted via Eloquent and dispatch a Laravel event after each save.

## Basic usage (non-Laravel)

```php
<?php

use Vendor\NeotelWebsocket\NeotelClient;
use Vendor\NeotelWebsocket\NeotelConfig;
use Vendor\NeotelWebsocket\Transport\TextalkWebSocketTransport;

$config = new NeotelConfig(
	websocketUrl: 'wss://pbx.example.test/socket',
	user: 'username',
	password: 'password'
);

$client = new NeotelClient(new TextalkWebSocketTransport(), $config);

$client->listen(function (array $payload, string $rawFrame, string $connectionId): void {
	// Handle decoded Neotel event payload here.
});
```

## Laravel usage

The package auto-discovers `Vendor\\NeotelWebsocket\\Laravel\\NeotelServiceProvider`.

Publish package config:

```bash
php artisan vendor:publish --tag=neotel-websocket-config
```

Publish package migrations (optional if you prefer published migrations):

```bash
php artisan vendor:publish --tag=neotel-websocket-migrations
```

Run migrations:

```bash
php artisan migrate
```

Required config values in `.env`:

```bash
NEOTEL_ENABLED=true
NEOTEL_WEBSOCKET_URL=wss://ix3.neotel2000.com:10000/agent
NEOTEL_USER=your-user
NEOTEL_PASSWORD=your-password
NEOTEL_RECORD_CALL_EVENTS=true
```

Run listener:

```bash
php artisan neotel:listen
```

When a call event is recorded, the package dispatches:

- `Vendor\\NeotelWebsocket\\Laravel\\Events\\NeotelCallEventRecorded`

This lets consumers register listeners/subscribers and implement custom handling without changing package internals.
