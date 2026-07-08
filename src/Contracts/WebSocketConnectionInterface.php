<?php

namespace Vendor\NeotelWebsocket\Contracts;

interface WebSocketConnectionInterface
{
    public function send(string $payload): void;

    public function receive(): string;

    public function close(): void;
}
