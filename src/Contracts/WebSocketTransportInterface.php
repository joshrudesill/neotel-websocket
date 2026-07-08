<?php

namespace Vendor\NeotelWebsocket\Contracts;

interface WebSocketTransportInterface
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function connect(string $url, array $options = []): WebSocketConnectionInterface;
}
