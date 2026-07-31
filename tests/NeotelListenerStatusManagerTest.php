<?php

namespace Vendor\NeotelWebsocket\Tests;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\TestCase;
use Vendor\NeotelWebsocket\Laravel\Events\NeotelListenerStatusChanged;
use Vendor\NeotelWebsocket\Laravel\Support\NeotelListenerStatusManager;

class NeotelListenerStatusManagerTest extends TestCase
{
    public function test_status_returns_idle_snapshot_when_cache_is_empty(): void
    {
        $cache = $this->createMock(CacheRepository::class);
        $cache->method('get')->willReturn(null);

        $manager = new NeotelListenerStatusManager(
            $cache,
            $this->config(),
            $this->createMock(Dispatcher::class),
        );

        $status = $manager->status();

        $this->assertSame('idle', $status['state']);
        $this->assertNull($status['heartbeat_at']);
    }

    public function test_set_status_caches_snapshot_and_dispatches_transition_event(): void
    {
        $storedStatus = null;
        $cache = $this->createMock(CacheRepository::class);
        $cache->method('get')->willReturnCallback(static fn (): ?array => $storedStatus);
        $cache->expects($this->once())
            ->method('put')
            ->with(
                'custom:neotel:status',
                $this->callback(function (array $status) use (&$storedStatus): bool {
                    $storedStatus = $status;

                    return $status['state'] === 'connecting';
                }),
                45,
            );

        $events = $this->createMock(Dispatcher::class);
        $events->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static fn (object $event): bool =>
                $event instanceof NeotelListenerStatusChanged
                && $event->status['state'] === 'connecting'
            ));

        $manager = new NeotelListenerStatusManager(
            $cache,
            $this->config([
                'neotel-websocket.status_cache_key' => 'custom:neotel:status',
                'neotel-websocket.status_cache_ttl_seconds' => 45,
            ]),
            $events,
        );

        $status = $manager->setStatus('connecting', 'Connecting...', ['pid' => 123]);

        $this->assertSame(123, $status['pid']);
        $this->assertNotNull($status['updated_at']);
    }

    public function test_touch_heartbeat_refreshes_cache_without_dispatching_event(): void
    {
        $currentStatus = [
            'state' => 'connected',
            'message' => 'Listener connected.',
            'heartbeat_at' => null,
        ];
        $cache = $this->createMock(CacheRepository::class);
        $cache->method('get')->willReturn($currentStatus);
        $cache->expects($this->once())
            ->method('put')
            ->with(
                'neotel:listener:status',
                $this->callback(static fn (array $status): bool =>
                    $status['last_event_type'] === 'update'
                    && $status['heartbeat_at'] !== null
                ),
                120,
            );

        $events = $this->createMock(Dispatcher::class);
        $events->expects($this->never())->method('dispatch');

        $manager = new NeotelListenerStatusManager($cache, $this->config(), $events);
        $manager->touchHeartbeat(['last_event_type' => 'update']);
    }

    public function test_disabled_status_tracking_has_no_side_effects(): void
    {
        $cache = $this->createMock(CacheRepository::class);
        $cache->expects($this->never())->method('put');

        $events = $this->createMock(Dispatcher::class);
        $events->expects($this->never())->method('dispatch');

        $manager = new NeotelListenerStatusManager(
            $cache,
            $this->config(['neotel-websocket.status_enabled' => false]),
            $events,
        );

        $status = $manager->setStatus('connected', 'Listener connected.');

        $this->assertSame('idle', $status['state']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function config(array $overrides = []): ConfigRepository
    {
        $values = array_merge([
            'neotel-websocket.status_enabled' => true,
            'neotel-websocket.status_cache_key' => 'neotel:listener:status',
            'neotel-websocket.status_cache_ttl_seconds' => 120,
        ], $overrides);

        $config = $this->createMock(ConfigRepository::class);
        $config->method('get')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => $values[$key] ?? $default,
        );

        return $config;
    }
}