# neotel-websocket

Framework-agnostic Neotel websocket client with a Laravel integration layer. Handles the
connection/authentication/reconnect lifecycle, decodes incoming frames, and optionally
persists and/or dispatches Laravel events for call activity and system/connection activity,
independent of any specific application's domain logic.

## Features

- Core websocket client (`NeotelClient`) with welcome/auth-plain handshake, automatic
  reconnect with exponential backoff, and configurable read timeouts.
- Transport abstraction (`WebSocketTransportInterface` / `WebSocketConnectionInterface`) so the
  underlying websocket implementation can be swapped or faked in tests. Ships with a default
  transport backed by `textalk/websocket`.
- Laravel integration: auto-discovered service provider, publishable config, publishable
  migrations, and a `neotel:listen` artisan command.
- Two independent recorder/event pairs so consuming apps can subscribe with normal Laravel
  listeners instead of hand-rolling payload parsing:
  - `NeotelCallEventRecorder` -> `NeotelCallEventRecorded` for the 5 call actions (`new-call`,
    `call-state`, `hangup`, `start-moh`/`stop-moh`, `start-record`/`end-record`).
  - `NeotelSystemEventRecorder` -> `NeotelSystemEventRecorded` for everything else (`welcome`,
    `auth-resp`, `init`, `extension-status`, and any unrecognized frame).
- Raw persistence (`NEOTEL_DB`) and Laravel event dispatch (`NEOTEL_EVENTS`) are independent
  toggles, so a consuming app can dispatch-only (and own its own tables/projections) without
  the package writing anything to the database.

## Requirements

- PHP `^8.2`
- `ext-json`
- Laravel `illuminate/*` `^12.0` (console, database, events, support) for the Laravel
  integration layer. The core client (`NeotelClient`, `NeotelConfig`, transport contracts) has
  no framework dependency and can be used standalone.
- `textalk/websocket` `^1.6` (used by the bundled default transport)

## Installation

### From Packagist / VCS

```bash
composer require vendor/neotel-websocket
```

### As a local path package (for development against a consuming app)

In the consuming app's `composer.json`:

```jsonc
{
    "repositories": [
        { "type": "path", "url": "../neotel-websocket" }
    ],
    "require": {
        "vendor/neotel-websocket": "*@dev"
    }
}
```

```bash
composer update vendor/neotel-websocket --with-all-dependencies
```

## Basic usage (framework-agnostic)

```php
<?php

use Vendor\NeotelWebsocket\NeotelClient;
use Vendor\NeotelWebsocket\NeotelConfig;
use Vendor\NeotelWebsocket\Transport\TextalkWebSocketTransport;

$config = new NeotelConfig(
    websocketUrl: 'wss://pbx.example.test/socket',
    user: 'username',
    password: 'password',
);

$client = new NeotelClient(new TextalkWebSocketTransport(), $config);

$client->listen(function (array $payload, string $rawFrame, string $connectionId): void {
    // Handle decoded Neotel event payload here.
});
```

`NeotelClient::listen()` accepts optional `maxEvents`/`maxReconnectAttempts` bounds (both `0` =
unlimited), which is useful for scripted/bounded runs and tests.

## Laravel integration

The package auto-discovers `Vendor\NeotelWebsocket\Laravel\NeotelServiceProvider`.

Publish the config (optional — the package works with defaults merged automatically):

```bash
php artisan vendor:publish --tag=neotel-websocket-config
```

Publish migrations (optional — only needed if you want the package's own
`neotel_call_events`/`neotel_system_events` tables; skip if your app already owns equivalent
tables and set `NEOTEL_LOAD_MIGRATIONS=false`):

```bash
php artisan vendor:publish --tag=neotel-websocket-migrations
php artisan migrate
```

Run the bundled listener command:

```bash
php artisan neotel:listen --max-events=0 --max-reconnect-attempts=0
```

### Configuration reference

All values are read from `config/neotel-websocket.php`, which merges automatically even
without publishing. Environment variables:

| Env var | Default | Description |
| --- | --- | --- |
| `NEOTEL_ENABLED` | `false` | Master on/off switch checked by the bundled command. |
| `NEOTEL_WEBSOCKET_URL` | `''` | Websocket endpoint to connect to. |
| `NEOTEL_USER` | `''` | Username sent in the `auth-plain` frame. |
| `NEOTEL_PASSWORD` | `''` | Password sent in the `auth-plain` frame. |
| `NEOTEL_VERIFY_SSL` | `true` | Verify TLS peer/hostname on connect. |
| `NEOTEL_READ_TIMEOUT` | `30` | Socket read timeout (seconds) before treating the connection as idle. |
| `NEOTEL_INITIAL_BACKOFF_SECONDS` | `1` | Initial reconnect backoff. |
| `NEOTEL_MAX_BACKOFF_SECONDS` | `30` | Max reconnect backoff (exponential up to this ceiling). |
| `NEOTEL_USER_AGENT` | `vendor-neotel-websocket/0.1` | `User-Agent` header sent on connect. |
| `NEOTEL_LOG_CHANNEL` | `null` | Optional dedicated log channel used by the package's own `neotel:listen` command. |
| `NEOTEL_EVENTS` (alias `NEOTEL_RECORD_CALL_EVENTS`) | `true` | Dispatch `NeotelCallEventRecorded`/`NeotelSystemEventRecorded` Laravel events. |
| `NEOTEL_DB` | `true` | Persist raw events via the package's own Eloquent models. |
| `NEOTEL_REGISTER_COMMAND` | `true` | Register the package's `neotel:listen` command. Set `false` if your app defines its own. |
| `NEOTEL_LOAD_MIGRATIONS` | `true` | Auto-load the package's migrations. Set `false` if your app already owns the tables. |

`NEOTEL_EVENTS` and `NEOTEL_DB` are independent — enable either one alone, both together, or
neither. If `NEOTEL_DB=false`, dispatched events still fire but their `record` property is
`null`.

### Events

Both events share the same shape:

```php
public function __construct(
    public readonly ?Model $record,       // NeotelCallEvent|NeotelSystemEvent, null if NEOTEL_DB=false
    public readonly array $payload,        // decoded frame
    public readonly string $rawFrame,      // original raw websocket frame
    public readonly string $connectionId,  // id generated per websocket connection
) {}
```

- `Vendor\NeotelWebsocket\Laravel\Events\NeotelCallEventRecorded` — dispatched for
  `type=update` frames whose `action` is one of `new-call`, `call-state`, `hangup`,
  `start-moh`, `stop-moh`, `start-record`, `end-record`.
- `Vendor\NeotelWebsocket\Laravel\Events\NeotelSystemEventRecorded` — dispatched for every
  other frame (`welcome`, `auth-resp`, `init`, `update` with any other action, or unrecognized
  types).

Subscribe with a normal Laravel listener; no manual registration is required if your app uses
event auto-discovery:

```php
namespace App\Listeners;

use Vendor\NeotelWebsocket\Laravel\Events\NeotelCallEventRecorded;

class HandleNeotelCallEvent
{
    public function handle(NeotelCallEventRecorded $event): void
    {
        // $event->payload['action'], $event->payload['callid'], etc.
    }
}
```

### Recorders

If you want to invoke the recording/dispatch logic yourself (for example, when your app
already owns the websocket connection loop and just wants to route frames through this
package), resolve the recorders from the container and call `record()` directly:

```php
use Vendor\NeotelWebsocket\Laravel\Recorders\NeotelCallEventRecorder;
use Vendor\NeotelWebsocket\Laravel\Recorders\NeotelSystemEventRecorder;

$callEventRecorder->record($payload, $rawFrame, $connectionId, persistToDatabase: false, dispatchLaravelEvent: true);
$systemEventRecorder->record($payload, $rawFrame, $connectionId, persistToDatabase: false, dispatchLaravelEvent: true);
```

Each recorder ignores frames outside its own concern (returns `null` without side effects), so
it's safe to call both unconditionally for every received frame. Set `persistToDatabase: false`
if your app's tables don't match the package's default schema and you only want the dispatched
event.

### Models & migrations

| Model | Table | Migration |
| --- | --- | --- |
| `Vendor\NeotelWebsocket\Laravel\Models\NeotelCallEvent` | `neotel_call_events` | `2026_07_08_000000_create_neotel_call_events_table.php` |
| `Vendor\NeotelWebsocket\Laravel\Models\NeotelSystemEvent` | `neotel_system_events` | `2026_07_09_000000_create_neotel_system_events_table.php` |

### Extending the transport

Bind your own implementation of `WebSocketTransportInterface`/`WebSocketConnectionInterface` in
your service provider to swap the underlying websocket library, or to provide a fake in tests:

```php
interface WebSocketTransportInterface
{
    public function connect(string $url, array $options = []): WebSocketConnectionInterface;
}

interface WebSocketConnectionInterface
{
    public function send(string $payload): void;
    public function receive(): string;
    public function close(): void;
}
```

### Exceptions

- `Vendor\NeotelWebsocket\Exceptions\NeotelConnectionException` — thrown on handshake/auth
  failure or missing configuration.
- `Vendor\NeotelWebsocket\Exceptions\WebSocketReadTimeoutException` — thrown by the default
  transport's `receive()` on a socket read timeout; treated as idle by `NeotelClient::listen()`.

## Testing

```bash
composer install
vendor/bin/phpunit
```

Tests use fake transport/connection implementations (see `tests/NeotelClientTest.php`) — no
real network access is required or performed.

## Current status

- Core listener, auth flow, and reconnect/backoff are implemented and covered by tests using
  fake transports.
- Default transport implemented via `textalk/websocket`.
- Laravel service provider, publishable config/migrations, and `neotel:listen` command included.
- Call events and system/connection events are each persisted (optionally) and dispatched as
  independent Laravel events.

## License

MIT.
