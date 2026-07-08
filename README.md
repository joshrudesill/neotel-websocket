# neotel-websocket

Minimal Neotel websocket client package with transport abstraction.

## Current status

- Core listener and auth flow are implemented.
- Default websocket transport is implemented via `textalk/websocket`.
- Smoke tests cover handshake flow and reconnect behavior with fake transport classes.

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

Laravel integration is not added yet.
