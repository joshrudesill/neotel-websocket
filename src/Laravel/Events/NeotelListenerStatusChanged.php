<?php

namespace Vendor\NeotelWebsocket\Laravel\Events;

class NeotelListenerStatusChanged
{
    /**
     * @param  array<string, mixed>  $status
     */
    public function __construct(public readonly array $status)
    {
    }
}