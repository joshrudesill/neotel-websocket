<?php

namespace Vendor\NeotelWebsocket;

final class NeotelConfig
{
    public function __construct(
        public readonly string $websocketUrl,
        public readonly string $user,
        public readonly string $password,
        public readonly bool $verifySsl = true,
        public readonly int $readTimeoutSeconds = 30,
        public readonly int $initialBackoffSeconds = 1,
        public readonly int $maxBackoffSeconds = 30,
        public readonly string $userAgent = 'vendor-neotel-websocket/0.1',
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            websocketUrl: trim((string) ($config['websocket_url'] ?? '')),
            user: trim((string) ($config['user'] ?? '')),
            password: (string) ($config['password'] ?? ''),
            verifySsl: (bool) ($config['verify_ssl'] ?? true),
            readTimeoutSeconds: max(1, (int) ($config['read_timeout'] ?? 30)),
            initialBackoffSeconds: max(1, (int) ($config['initial_backoff_seconds'] ?? 1)),
            maxBackoffSeconds: max(1, (int) ($config['max_backoff_seconds'] ?? 30)),
            userAgent: trim((string) ($config['user_agent'] ?? 'vendor-neotel-websocket/0.1')),
        );
    }
}
