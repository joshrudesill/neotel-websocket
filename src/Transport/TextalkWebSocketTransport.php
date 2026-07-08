<?php

namespace Vendor\NeotelWebsocket\Transport;

use Vendor\NeotelWebsocket\Contracts\WebSocketTransportInterface;
use WebSocket\Client;

final class TextalkWebSocketTransport implements WebSocketTransportInterface
{
    public function connect(string $url, array $options = []): TextalkWebSocketConnection
    {
        $verifySsl = (bool) ($options['verify_ssl'] ?? true);
        $timeout = max(1, (int) ($options['read_timeout'] ?? 30));

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => $verifySsl,
                'verify_peer_name' => $verifySsl,
            ],
        ]);

        $client = new Client($url, [
            'timeout' => $timeout,
            'context' => $context,
            'headers' => (array) ($options['headers'] ?? []),
        ]);

        return new TextalkWebSocketConnection($client);
    }
}
