<?php

use Illuminate\Support\Facades\Route;
use Vendor\NeotelWebsocket\Laravel\Http\Controllers\NeotelConfigController;

Route::prefix((string) config('neotel-websocket.api_prefix', 'api/neotel-websocket'))->group(function (): void {
    Route::get('/config', [NeotelConfigController::class, 'show']);
    Route::put('/config', [NeotelConfigController::class, 'replace']);
});
