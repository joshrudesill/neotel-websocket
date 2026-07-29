<?php

namespace Vendor\NeotelWebsocket\Laravel\Support;

final class NeotelSettings
{
    /**
     * @var list<string>
     */
    public const KEYS = [
        'enabled',
        'websocket_url',
        'user',
        'password',
        'verify_ssl',
        'read_timeout',
        'initial_backoff_seconds',
        'max_backoff_seconds',
        'user_agent',
        'log_channel',
        'events_enabled',
        'db_enabled',
        'register_command',
        'load_migrations',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'websocket_url' => '',
            'user' => '',
            'password' => '',
            'verify_ssl' => true,
            'read_timeout' => 30,
            'initial_backoff_seconds' => 1,
            'max_backoff_seconds' => 30,
            'user_agent' => 'vendor-neotel-websocket/0.1',
            'log_channel' => null,
            'events_enabled' => true,
            'db_enabled' => true,
            'register_command' => true,
            'load_migrations' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function normalize(array $values): array
    {
        return [
            'enabled' => (bool) ($values['enabled'] ?? false),
            'websocket_url' => trim((string) ($values['websocket_url'] ?? '')),
            'user' => trim((string) ($values['user'] ?? '')),
            'password' => (string) ($values['password'] ?? ''),
            'verify_ssl' => (bool) ($values['verify_ssl'] ?? true),
            'read_timeout' => max(1, (int) ($values['read_timeout'] ?? 30)),
            'initial_backoff_seconds' => max(1, (int) ($values['initial_backoff_seconds'] ?? 1)),
            'max_backoff_seconds' => max(1, (int) ($values['max_backoff_seconds'] ?? 30)),
            'user_agent' => trim((string) ($values['user_agent'] ?? 'vendor-neotel-websocket/0.1')),
            'log_channel' => self::normalizeLogChannel($values['log_channel'] ?? null),
            'events_enabled' => (bool) ($values['events_enabled'] ?? true),
            'db_enabled' => (bool) ($values['db_enabled'] ?? true),
            'register_command' => (bool) ($values['register_command'] ?? true),
            'load_migrations' => (bool) ($values['load_migrations'] ?? true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function mergeWithDefaults(array $values): array
    {
        return self::normalize(array_merge(self::defaults(), $values));
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function replacementValidationRules(): array
    {
        return [
            'enabled' => ['present', 'boolean'],
            'websocket_url' => ['present', 'string'],
            'user' => ['present', 'string'],
            'password' => ['present', 'string'],
            'verify_ssl' => ['present', 'boolean'],
            'read_timeout' => ['present', 'integer', 'min:1'],
            'initial_backoff_seconds' => ['present', 'integer', 'min:1'],
            'max_backoff_seconds' => ['present', 'integer', 'min:1'],
            'user_agent' => ['present', 'string'],
            'log_channel' => ['present', 'nullable', 'string'],
            'events_enabled' => ['present', 'boolean'],
            'db_enabled' => ['present', 'boolean'],
            'register_command' => ['present', 'boolean'],
            'load_migrations' => ['present', 'boolean'],
        ];
    }

    public static function encode(string $key, mixed $value): ?string
    {
        return match ($key) {
            'enabled',
            'verify_ssl',
            'events_enabled',
            'db_enabled',
            'register_command',
            'load_migrations' => ((bool) $value) ? '1' : '0',
            'read_timeout',
            'initial_backoff_seconds',
            'max_backoff_seconds' => (string) max(1, (int) $value),
            'log_channel' => self::normalizeLogChannel($value),
            default => trim((string) $value),
        };
    }

    public static function decode(string $key, ?string $value): mixed
    {
        return match ($key) {
            'enabled',
            'verify_ssl',
            'events_enabled',
            'db_enabled',
            'register_command',
            'load_migrations' => filter_var($value, FILTER_VALIDATE_BOOL),
            'read_timeout',
            'initial_backoff_seconds',
            'max_backoff_seconds' => max(1, (int) $value),
            'log_channel' => self::normalizeLogChannel($value),
            default => trim((string) ($value ?? '')),
        };
    }

    private static function normalizeLogChannel(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
