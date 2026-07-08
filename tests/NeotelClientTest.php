<?php

namespace Vendor\NeotelWebsocket\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vendor\NeotelWebsocket\Contracts\WebSocketConnectionInterface;
use Vendor\NeotelWebsocket\Contracts\WebSocketTransportInterface;
use Vendor\NeotelWebsocket\NeotelClient;
use Vendor\NeotelWebsocket\NeotelConfig;

class NeotelClientTest extends TestCase
{
    public function test_listen_processes_event_after_welcome_and_auth(): void
    {
        $connection = new FakeConnection([
            '{"type":"welcome","auth":"required"}',
            '{"type":"auth-ok","status":"ok"}',
            '{"type":"call","server":"pbx-1"}',
        ]);

        $transport = new FakeTransport([$connection]);
        $client = new NeotelClient($transport, $this->config());

        $seenTypes = [];

        $client->listen(function (array $payload) use (&$seenTypes): void {
            $seenTypes[] = (string) ($payload['type'] ?? 'unknown');
        }, maxEvents: 1, maxReconnectAttempts: 1);

        $this->assertSame(['welcome', 'auth-ok', 'call'], $seenTypes);
        $this->assertCount(1, $connection->sentPayloads);
    }

    public function test_listen_reconnects_after_initial_connection_failure(): void
    {
        $transport = new FakeTransport([
            new RuntimeException('socket down'),
            new FakeConnection([
                '{"type":"welcome","auth":"required"}',
                '{"type":"auth-ok","status":"ok"}',
                '{"type":"call","server":"pbx-1"}',
            ]),
        ]);

        $client = new NeotelClient($transport, $this->config());

        $events = [];

        $client->listen(function (array $payload) use (&$events): void {
            $events[] = (string) ($payload['type'] ?? 'unknown');
        }, maxEvents: 1, maxReconnectAttempts: 2);

        $this->assertGreaterThanOrEqual(2, $transport->connectAttempts);
        $this->assertContains('call', $events);
    }

    private function config(): NeotelConfig
    {
        return new NeotelConfig(
            websocketUrl: 'wss://example.test/socket',
            user: 'demo-user',
            password: 'demo-pass',
            verifySsl: true,
            readTimeoutSeconds: 30,
            initialBackoffSeconds: 1,
            maxBackoffSeconds: 1,
            userAgent: 'tests/0.1'
        );
    }
}

final class FakeTransport implements WebSocketTransportInterface
{
    /**
     * @var list<FakeConnection|\Throwable>
     */
    private array $queue;

    public int $connectAttempts = 0;

    /**
     * @param  list<FakeConnection|\Throwable>  $queue
     */
    public function __construct(array $queue)
    {
        $this->queue = $queue;
    }

    public function connect(string $url, array $options = []): WebSocketConnectionInterface
    {
        $this->connectAttempts++;

        if ($this->queue === []) {
            throw new RuntimeException('No fake connection queued.');
        }

        $next = array_shift($this->queue);

        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }
}

final class FakeConnection implements WebSocketConnectionInterface
{
    /**
     * @var list<string>
     */
    private array $incomingFrames;

    /**
     * @var list<string>
     */
    public array $sentPayloads = [];

    public bool $closed = false;

    /**
     * @param  list<string>  $incomingFrames
     */
    public function __construct(array $incomingFrames)
    {
        $this->incomingFrames = $incomingFrames;
    }

    public function send(string $payload): void
    {
        $this->sentPayloads[] = $payload;
    }

    public function receive(): string
    {
        if ($this->incomingFrames === []) {
            throw new RuntimeException('No more incoming frames.');
        }

        $frame = array_shift($this->incomingFrames);

        return (string) $frame;
    }

    public function close(): void
    {
        $this->closed = true;
    }
}
