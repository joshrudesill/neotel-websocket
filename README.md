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
NEOTEL_EVENTS=true
NEOTEL_DB=true
```

Run listener:

```bash
php artisan neotel:listen
```

Call event handling toggles:

- `NEOTEL_EVENTS=true` dispatches Laravel events to your listeners.
- `NEOTEL_DB=true` persists call events into `neotel_call_events`.

You can enable either one independently or both together.

When enabled, the package dispatches:

- `Vendor\\NeotelWebsocket\\Laravel\\Events\\NeotelCallEventRecorded`

When `NEOTEL_DB=false`, the event still dispatches and the event `record` property is `null`.
