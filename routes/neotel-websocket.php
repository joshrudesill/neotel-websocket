<?php

use Illuminate\Support\Facades\Route;
use Vendor\NeotelWebsocket\Laravel\Http\Controllers\NeotelConfigController;
use Vendor\NeotelWebsocket\Laravel\Http\Controllers\NeotelListenerStatusController;

Route::prefix((string) config('neotel-websocket.api_prefix', 'api/neotel-websocket'))->group(function (): void {
    Route::get('/config', [NeotelConfigController::class, 'show']);
    Route::put('/config', [NeotelConfigController::class, 'replace']);

    if ((bool) config('neotel-websocket.status_route_enabled', false)) {
        Route::get('/listener/status', [NeotelListenerStatusController::class, 'show'])
            ->middleware((array) config('neotel-websocket.status_route_middleware', []));
    }
});
