<?php

namespace Vendor\NeotelWebsocket;

use Throwable;
use Vendor\NeotelWebsocket\Contracts\WebSocketConnectionInterface;
use Vendor\NeotelWebsocket\Contracts\WebSocketTransportInterface;
use Vendor\NeotelWebsocket\Exceptions\NeotelConnectionException;
use Vendor\NeotelWebsocket\Exceptions\WebSocketReadTimeoutException;

class NeotelClient
{
    public function __construct(
        private readonly WebSocketTransportInterface $transport,
        private readonly NeotelConfig $config,
    ) {}

    /**
     * @param  callable(array<string, mixed>, string, string): void|null  $onEvent
     */
    public function listen(?callable $onEvent = null, int $maxEvents = 0, int $maxReconnectAttempts = 0): void
    {
        $eventsProcessed = 0;
        $attempt = 0;

        while (true) {
            $connection = null;
            $connectionId = self::generateConnectionId();

            try {
                $connection = $this->connectWithCredentials($onEvent, $connectionId);
                $attempt = 0;

                while (true) {
                    $rawFrame = $this->receiveFrame($connection);

                    if ($rawFrame === null || $rawFrame === '') {
                        continue;
                    }

                    $payload = self::decodePayload($rawFrame);

                    if ($onEvent !== null) {
                        $onEvent($payload, $rawFrame, $connectionId);
                    }

                    $eventsProcessed++;
                    if ($maxEvents > 0 && $eventsProcessed >= $maxEvents) {
                        return;
                    }
                }
            } catch (Throwable $exception) {
                $attempt++;

                if ($maxReconnectAttempts > 0 && $attempt > $maxReconnectAttempts) {
                    throw new NeotelConnectionException(
                        'Maximum reconnect attempts reached for Neotel listener.',
                        previous: $exception
                    );
                }

                sleep($this->backoffSeconds($attempt));
            } finally {
                if ($connection instanceof WebSocketConnectionInterface) {
                    try {
                        $connection->close();
                    } catch (Throwable) {
                        // Ignore close failures during reconnect handling.
                    }
                }
            }
        }
    }

    /**
     * @param  callable(array<string, mixed>, string, string): void|null  $onEvent
     */
    public function connectWithCredentials(?callable $onEvent = null, ?string $connectionId = null): WebSocketConnectionInterface
    {
        $url = trim($this->config->websocketUrl);
        $user = trim($this->config->user);
        $password = (string) $this->config->password;
        $connectionId = $connectionId ?: self::generateConnectionId();

        if ($url === '') {
            throw new NeotelConnectionException('NEOTEL_WEBSOCKET_URL is required.');
        }

        if ($user === '' || $password === '') {
            throw new NeotelConnectionException('Both NEOTEL_USER and NEOTEL_PASSWORD are required.');
        }

        $connection = $this->transport->connect($url, [
            'verify_ssl' => $this->config->verifySsl,
            'read_timeout' => max(1, $this->config->readTimeoutSeconds),
            'headers' => [
                'User-Agent' => $this->config->userAgent,
            ],
        ]);

        $welcomeRaw = (string) $connection->receive();
        $welcomePayload = self::decodePayload($welcomeRaw);

        if ($onEvent !== null) {
            $onEvent($welcomePayload, $welcomeRaw, $connectionId);
        }

        if (($welcomePayload['type'] ?? null) !== 'welcome') {
            throw new NeotelConnectionException('Unexpected Neotel welcome frame.');
        }

        $authPayload = [
            'type' => 'auth-plain',
            'user' => $user,
            'passwd' => $password,
        ];

        $connection->send((string) json_encode($authPayload, JSON_THROW_ON_ERROR));

        $authResponseRaw = (string) $connection->receive();
        $authResponse = self::decodePayload($authResponseRaw);

        if ($onEvent !== null) {
            $onEvent($authResponse, $authResponseRaw, $connectionId);
        }

        if (self::isLikelyAuthFailure($authResponse)) {
            throw new NeotelConnectionException('Neotel rejected websocket authentication.');
        }

        return $connection;
    }

    /**
     * @return array<string, mixed>
     */
    public static function decodePayload(string $rawFrame): array
    {
        $decoded = json_decode($rawFrame, true);

        return is_array($decoded)
            ? $decoded
            : ['type' => 'raw', 'raw' => $rawFrame];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function redactSensitive(array $payload): array
    {
        $redacted = $payload;

        foreach (['passwd', 'password', 'token', 'access_token', 'refresh_token', 'secret'] as $key) {
            if (array_key_exists($key, $redacted)) {
                $redacted[$key] = '***redacted***';
            }
        }

        return $redacted;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function messageType(array $payload): string
    {
        $type = $payload['type'] ?? 'unknown';

        return is_string($type) && $type !== '' ? $type : 'unknown';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function isLikelyAuthFailure(array $payload): bool
    {
        $type = strtolower((string) ($payload['type'] ?? ''));
        $auth = strtolower((string) ($payload['auth'] ?? ''));
        $status = strtolower((string) ($payload['status'] ?? ''));
        $hasError = isset($payload['error']) && (string) $payload['error'] !== '';

        if ($type === '' && $auth === '' && $status === '' && ! $hasError) {
            return false;
        }

        return str_contains($type, 'fail')
            || str_contains($type, 'error')
            || str_contains($auth, 'fail')
            || str_contains($status, 'fail')
            || $hasError;
    }

    private function backoffSeconds(int $attempt): int
    {
        $initial = max(1, $this->config->initialBackoffSeconds);
        $max = max(1, $this->config->maxBackoffSeconds);
        $calculated = $initial * (2 ** max(0, $attempt - 1));

        return min($max, $calculated);
    }

    private function receiveFrame(WebSocketConnectionInterface $connection): ?string
    {
        try {
            return (string) $connection->receive();
        } catch (WebSocketReadTimeoutException) {
            // Treat timeout as idle connection and keep listening.
            return null;
        }
    }

    private static function generateConnectionId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
