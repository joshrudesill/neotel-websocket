<?php

namespace Vendor\NeotelWebsocket\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Vendor\NeotelWebsocket\Laravel\Support\NeotelListenerStatusManager;

class NeotelListenerStatusController
{
    public function __construct(private readonly NeotelListenerStatusManager $listenerStatus)
    {
    }

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => $this->listenerStatus->status(),
        ]);
    }
}