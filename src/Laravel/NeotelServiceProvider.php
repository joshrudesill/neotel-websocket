<?php

namespace Vendor\NeotelWebsocket\Laravel;

use Illuminate\Support\ServiceProvider;
use Vendor\NeotelWebsocket\Contracts\WebSocketTransportInterface;
use Vendor\NeotelWebsocket\Laravel\Console\Commands\NeotelListenCommand;
use Vendor\NeotelWebsocket\Laravel\Recorders\NeotelCallEventRecorder;
use Vendor\NeotelWebsocket\NeotelClient;
use Vendor\NeotelWebsocket\NeotelConfig;
use Vendor\NeotelWebsocket\Transport\TextalkWebSocketTransport;

class NeotelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/neotel-websocket.php', 'neotel-websocket');

        $this->app->singleton(WebSocketTransportInterface::class, TextalkWebSocketTransport::class);

        $this->app->singleton(NeotelConfig::class, function ($app): NeotelConfig {
            /** @var array<string, mixed> $config */
            $config = (array) $app['config']->get('neotel-websocket', []);

            return NeotelConfig::fromArray($config);
        });

        $this->app->singleton(NeotelClient::class, function ($app): NeotelClient {
            return new NeotelClient(
                $app->make(WebSocketTransportInterface::class),
                $app->make(NeotelConfig::class),
            );
        });

        $this->app->singleton(NeotelCallEventRecorder::class);
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../../config/neotel-websocket.php' => config_path('neotel-websocket.php'),
        ], 'neotel-websocket-config');

        $this->publishes([
            __DIR__.'/../../database/migrations/' => database_path('migrations'),
        ], 'neotel-websocket-migrations');

        if ((bool) config('neotel-websocket.load_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }

        if ((bool) config('neotel-websocket.register_command', true)) {
            $this->commands([
                NeotelListenCommand::class,
            ]);
        }
    }
}
