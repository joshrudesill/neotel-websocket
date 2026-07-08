<?php

namespace Vendor\NeotelWebsocket\Transport;

use Throwable;
use Vendor\NeotelWebsocket\Contracts\WebSocketConnectionInterface;
use Vendor\NeotelWebsocket\Exceptions\WebSocketReadTimeoutException;
use WebSocket\Client;
use WebSocket\TimeoutException;

final class TextalkWebSocketConnection implements WebSocketConnectionInterface
{
    public function __construct(
        private readonly Client $client,
    ) {}

    public function send(string $payload): void
    {
        $this->client->send($payload);
    }

    public function receive(): string
    {
        try {
            return (string) $this->client->receive();
        } catch (TimeoutException $exception) {
            throw new WebSocketReadTimeoutException('Websocket read timed out.', previous: $exception);
        }
    }

    public function close(): void
    {
        try {
            $this->client->close();
        } catch (Throwable) {
            // Closing failures are non-fatal during reconnect or shutdown paths.
        }
    }
}
