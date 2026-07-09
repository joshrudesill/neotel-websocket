<?php

namespace Vendor\NeotelWebsocket\Laravel\Recorders;

use Vendor\NeotelWebsocket\Laravel\Events\NeotelSystemEventRecorded;
use Vendor\NeotelWebsocket\Laravel\Models\NeotelSystemEvent;
use Vendor\NeotelWebsocket\NeotelClient;

class NeotelSystemEventRecorder
{
    /**
     * Any payload not matching one of these `type=update` actions is treated
     * as a "system" event (welcome, auth-resp, init, extension-status, and
     * any other/unrecognized frame). Keep in sync with
     * Vendor\NeotelWebsocket\Laravel\Recorders\NeotelCallEventRecorder::isCallEvent().
     */
    private const CALL_ACTIONS = [
        'new-call',
        'call-state',
        'hangup',
        'start-moh',
        'stop-moh',
        'start-record',
        'end-record',
    ];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        array $payload,
        string $rawFrame,
        string $connectionId,
        bool $persistToDatabase = true,
        bool $dispatchLaravelEvent = true,
    ): ?NeotelSystemEvent
    {
        if ($this->isCallEvent($payload)) {
            return null;
        }

        if (! $persistToDatabase && ! $dispatchLaravelEvent) {
            return null;
        }

        $record = null;

        if ($persistToDatabase) {
            $record = NeotelSystemEvent::query()->create([
                'connection_id' => $connectionId,
                'type' => $this->stringValue($payload, ['type']) ?? 'unknown',
                'action' => $this->stringValue($payload, ['action']),
                'server_name' => $this->stringValue($payload, ['server', 'servername']),
                'extension' => $this->stringValue($payload, ['extension']),
                'payload' => NeotelClient::redactSensitive($payload),
                'raw_payload' => $rawFrame,
                'occurred_at' => now(),
            ]);
        }

        if ($dispatchLaravelEvent) {
            event(new NeotelSystemEventRecorded($record, $payload, $rawFrame, $connectionId));
        }

        return $record;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isCallEvent(array $payload): bool
    {
        $type = strtolower((string) ($payload['type'] ?? ''));
        $action = strtolower((string) ($payload['action'] ?? ''));

        if ($type !== 'update') {
            return false;
        }

        return in_array($action, self::CALL_ACTIONS, true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $keys
     */
    private function stringValue(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }
}
