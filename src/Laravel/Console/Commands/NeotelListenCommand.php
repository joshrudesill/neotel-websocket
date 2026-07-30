<?php
namespace Vendor\NeotelWebsocket\Laravel\Console\Commands;

// use App\Services\Integrations\Neotel\NeotelListenerStatusManager;
use Illuminate\Console\Command;
use Throwable;
use Vendor\NeotelWebsocket\Laravel\Recorders\NeotelCallEventRecorder;
use Vendor\NeotelWebsocket\Laravel\Recorders\NeotelSystemEventRecorder;
use Vendor\NeotelWebsocket\NeotelClient;
use Vendor\NeotelWebsocket\NeotelConfig;

class NeotelListenCommand extends Command
{
    /**
     * Payload `action` values (for type=update frames) that are routed
     * through the neotel-websocket package's call event recorder/event
     * instead of the system event recorder. Keep in sync with
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

    protected $signature = 'neotel:listen
        {--max-events=0 : Exit after processing this many events (0 = infinite)}
        {--max-reconnect-attempts=0 : Stop after this many reconnect attempts (0 = infinite)}';

    protected $description = 'Connect to Neotel websocket, authenticate, and stream events to logs.';

    public function handle(
        NeotelClient $client,
        NeotelConfig $config,
        NeotelCallEventRecorder $callEventRecorder,
        NeotelSystemEventRecorder $systemEventRecorder,
        // NeotelListenerStatusManager $listenerStatus,
    ): int {
        if (! (bool) config('neotel-websocket.enabled', false)) {
            // $listenerStatus->setStatus('disabled', 'Listener is disabled.', [
            //     'detail' => 'Enable Neotel before starting the listener.',
            //     'pid' => null,
            //     'heartbeat_at' => null,
            //     'websocket_url' => $config->websocketUrl !== '' ? $config->websocketUrl : null,
            // ]);
            $this->error('Neotel listener is disabled. Set neotel-websocket.enabled=true to run this command.');

            return self::FAILURE;
        }

        if ($config->websocketUrl === '' || $config->user === '' || $config->password === '') {
            // $listenerStatus->setStatus('misconfigured', 'Listener credentials are incomplete.', [
            //     'detail' => 'websocket_url, user, and password must all be configured.',
            //     'pid' => null,
            //     'heartbeat_at' => null,
            //     'websocket_url' => $config->websocketUrl !== '' ? $config->websocketUrl : null,
            // ]);
            $this->error('NEOTEL_WEBSOCKET_URL, NEOTEL_USER, and NEOTEL_PASSWORD must all be configured.');

            return self::FAILURE;
        }

        $maxEvents = max(0, (int) $this->option('max-events'));
        $maxReconnectAttempts = max(0, (int) $this->option('max-reconnect-attempts'));

        $this->info('Starting Neotel listener...');
        $this->line(sprintf('Endpoint: %s', $config->websocketUrl));
        $this->line(sprintf('User: %s', $config->user));
        $this->line(sprintf('Max events: %d', $maxEvents));

        // All frames are dispatched as Laravel events via the package recorders
        // and handled by App\Listeners\Neotel\NeotelCallEventListener and
        // App\Listeners\Neotel\NeotelSystemEventListener. Raw persistence into
        // the package's own event models is intentionally disabled: CRM's
        // `neotel_call_events`/`neotel_system_events` tables have already been
        // restructured for domain projections and don't match the columns the
        // package's raw persistence expects.
        $dispatchEvents = (bool) config('neotel-websocket.events_enabled', true);

        // $listenerStatus->setStatus('connecting', 'Connecting to Neotel websocket…', [
        //     'detail' => 'Starting websocket handshake.',
        //     'pid' => getmypid() ?: null,
        //     'heartbeat_at' => null,
        //     'last_event_type' => null,
        //     'last_event_action' => null,
        //     'websocket_url' => $config->websocketUrl,
        // ]);

        try {
            $client->listen(function (array $payload, string $rawFrame, string $connectionId) use ($callEventRecorder, $systemEventRecorder, $dispatchEvents): void {
                $type = (string) ($payload['type'] ?? 'unknown');
                $action = (string) ($payload['action'] ?? '');

                if ($type === 'welcome') {
                    // $listenerStatus->setStatus('authenticating', 'Authenticating with Neotel…', [
                    //     'pid' => getmypid() ?: null,
                    //     'last_event_type' => $type,
                    //     'last_event_action' => $action !== '' ? $action : null,
                    // ]);
                } elseif (($type === 'auth-ok' || $type === 'auth-resp') && ! NeotelClient::isLikelyAuthFailure($payload)) {
                    // $listenerStatus->setStatus('connected', 'Listener connected.', [
                    //     'pid' => getmypid() ?: null,
                    //     'last_event_type' => $type,
                    //     'last_event_action' => $action !== '' ? $action : null,
                    // ]);
                    // $listenerStatus->touchHeartbeat();
                } else {
                    // $listenerStatus->touchHeartbeat([
                    //     'state' => 'connected',
                    //     'message' => 'Listener connected.',
                    //     'pid' => getmypid() ?: null,
                    //     'last_event_type' => $type,
                    //     'last_event_action' => $action !== '' ? $action : null,
                    // ]);
                }

                if ($type === 'update' && in_array($action, self::CALL_ACTIONS, true)) {
                    $callEventRecorder->record(
                        $payload,
                        $rawFrame,
                        $connectionId,
                        persistToDatabase: false,
                        dispatchLaravelEvent: $dispatchEvents,
                    );
                } else {
                    $systemEventRecorder->record(
                        $payload,
                        $rawFrame,
                        $connectionId,
                        persistToDatabase: false,
                        dispatchLaravelEvent: $dispatchEvents,
                    );
                }

                $server = (string) ($payload['server'] ?? 'n/a');
                $actionSegment = $action !== '' ? sprintf(' action=%s', $action) : '';

                $this->line(sprintf('[%s] event=%s%s server=%s', now()->toDateTimeString(), $type, $actionSegment, $server));
            }, $maxEvents, $maxReconnectAttempts);

            $this->info('Neotel listener finished successfully.');

            // $listenerStatus->setStatus('stopped', 'Listener stopped.', [
            //     'detail' => 'The listener process exited normally.',
            //     'pid' => null,
            // ]);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            // $listenerStatus->setStatus('failed', 'Listener failed.', [
            //     'detail' => $exception->getMessage(),
            //     'pid' => null,
            // ]);
            $this->error('Neotel listener failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
