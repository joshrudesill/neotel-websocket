<?php

namespace Vendor\NeotelWebsocket\Laravel\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Vendor\NeotelWebsocket\Laravel\Events\NeotelListenerStatusChanged;

class NeotelListenerStatusManager
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly ConfigRepository $config,
        private readonly Dispatcher $events,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $status = $this->cache->get($this->cacheKey());

        return is_array($status) ? $status : $this->defaultStatus();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function setStatus(string $state, string $message, array $context = []): array
    {
        if (! $this->enabled()) {
            return $this->defaultStatus();
        }

        $status = array_merge($this->status(), $context, [
            'state' => $state,
            'message' => $message,
            'updated_at' => now()->toIso8601String(),
        ]);

        $this->store($status);
        $this->events->dispatch(new NeotelListenerStatusChanged($status));

        return $status;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function touchHeartbeat(array $context = []): array
    {
        if (! $this->enabled()) {
            return $this->defaultStatus();
        }

        $status = array_merge($this->status(), $context, [
            'heartbeat_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);

        $this->store($status);

        return $status;
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function store(array $status): void
    {
        $ttl = max(1, (int) $this->config->get('neotel-websocket.status_cache_ttl_seconds', 120));

        $this->cache->put($this->cacheKey(), $status, $ttl);
    }

    private function enabled(): bool
    {
        return (bool) $this->config->get('neotel-websocket.status_enabled', false);
    }

    private function cacheKey(): string
    {
        return (string) $this->config->get('neotel-websocket.status_cache_key', 'neotel:listener:status');
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultStatus(): array
    {
        return [
            'state' => 'idle',
            'message' => 'Neotel listener has not reported a status.',
            'detail' => null,
            'pid' => null,
            'updated_at' => null,
            'heartbeat_at' => null,
            'last_event_type' => null,
            'last_event_action' => null,
            'websocket_url' => null,
        ];
    }
}