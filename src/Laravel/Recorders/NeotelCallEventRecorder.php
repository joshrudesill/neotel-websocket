<?php

namespace Vendor\NeotelWebsocket\Laravel\Recorders;

use Vendor\NeotelWebsocket\Laravel\Events\NeotelCallEventRecorded;
use Vendor\NeotelWebsocket\Laravel\Models\NeotelCallEvent;
use Vendor\NeotelWebsocket\NeotelClient;

class NeotelCallEventRecorder
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        array $payload,
        string $rawFrame,
        string $connectionId,
        bool $persistToDatabase = true,
        bool $dispatchLaravelEvent = true,
    ): ?NeotelCallEvent
    {
        if (! $this->isCallEvent($payload)) {
            return null;
        }

        $callid = $this->stringValue($payload, ['callid']);
        if ($callid === null) {
            return null;
        }

        if (! $persistToDatabase && ! $dispatchLaravelEvent) {
            return null;
        }

        $record = null;

        if ($persistToDatabase) {
            $record = NeotelCallEvent::query()->create([
                'connection_id' => $connectionId,
                'callid' => $callid,
                'action' => $this->stringValue($payload, ['action']) ?? 'unknown',
                'server_name' => $this->stringValue($payload, ['server']),
                'extension' => $this->stringValue($payload, ['extension']),
                'state_code' => $this->stringValue($payload, ['state']),
                'state_desc' => $this->stringValue($payload, ['statedesc']),
                'cause' => $this->stringValue($payload, ['cause']),
                'payload' => NeotelClient::redactSensitive($payload),
                'raw_payload' => $rawFrame,
                'occurred_at' => now(),
            ]);
        }

        if ($dispatchLaravelEvent) {
            event(new NeotelCallEventRecorded($record, $payload, $rawFrame, $connectionId));
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

        return in_array($action, [
            'new-call',
            'call-state',
            'hangup',
            'start-moh',
            'stop-moh',
            'start-record',
            'end-record',
        ], true);
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
