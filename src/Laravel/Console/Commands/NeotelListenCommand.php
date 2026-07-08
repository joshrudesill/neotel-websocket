<?php

namespace Vendor\NeotelWebsocket\Laravel\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;
use Vendor\NeotelWebsocket\Laravel\Recorders\NeotelCallEventRecorder;
use Vendor\NeotelWebsocket\NeotelClient;
use Vendor\NeotelWebsocket\NeotelConfig;

class NeotelListenCommand extends Command
{
    protected $signature = 'neotel:listen
        {--max-events=0 : Exit after processing this many events (0 = infinite)}
        {--max-reconnect-attempts=0 : Stop after this many reconnect attempts (0 = infinite)}';

    protected $description = 'Connect to Neotel websocket, authenticate, and stream events.';

    public function handle(NeotelClient $client, NeotelConfig $config, NeotelCallEventRecorder $recorder): int
    {
        if (! (bool) config('neotel-websocket.enabled', false)) {
            $this->error('Neotel listener is disabled. Set NEOTEL_ENABLED=true to run this command.');

            return self::FAILURE;
        }

        if ($config->websocketUrl === '' || $config->user === '' || $config->password === '') {
            $this->error('NEOTEL_WEBSOCKET_URL, NEOTEL_USER, and NEOTEL_PASSWORD must all be configured.');

            return self::FAILURE;
        }

        $maxEvents = max(0, (int) $this->option('max-events'));
        $maxReconnectAttempts = max(0, (int) $this->option('max-reconnect-attempts'));

        $this->info('Starting Neotel listener...');
        $this->line(sprintf('Endpoint: %s', $config->websocketUrl));
        $this->line(sprintf('User: %s', $config->user));
        $this->line(sprintf('Max events: %d', $maxEvents));

        $channel = config('neotel-websocket.log_channel');
        $dispatchEvents = (bool) config('neotel-websocket.events_enabled', true);
        $persistEventsToDatabase = (bool) config('neotel-websocket.db_enabled', true);

        try {
            $client->listen(function (array $payload, string $rawFrame, string $connectionId) use ($channel, $dispatchEvents, $persistEventsToDatabase, $recorder): void {
                $recorder->record(
                    $payload,
                    $rawFrame,
                    $connectionId,
                    persistToDatabase: $persistEventsToDatabase,
                    dispatchLaravelEvent: $dispatchEvents,
                );

                $type = (string) ($payload['type'] ?? 'unknown');
                $server = (string) ($payload['server'] ?? 'n/a');
                $action = (string) ($payload['action'] ?? '');
                $actionSegment = $action !== '' ? sprintf(' action=%s', $action) : '';

                $line = sprintf('[%s] event=%s%s server=%s', now()->toDateTimeString(), $type, $actionSegment, $server);
                $this->line($line);

                $context = [
                    'connection_id' => $connectionId,
                    'event_type' => $type,
                    'server' => $server,
                    'payload' => NeotelClient::redactSensitive($payload),
                    'raw_payload' => $rawFrame,
                ];

                if (is_string($channel) && $channel !== '') {
                    Log::channel($channel)->info('Neotel event received.', $context);

                    return;
                }

                Log::info('Neotel event received.', $context);
            }, $maxEvents, $maxReconnectAttempts);

            $this->info('Neotel listener finished successfully.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Neotel listener failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
