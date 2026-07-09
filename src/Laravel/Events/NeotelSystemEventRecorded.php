<?php

namespace Vendor\NeotelWebsocket\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Vendor\NeotelWebsocket\Laravel\Models\NeotelSystemEvent;

class NeotelSystemEventRecorded
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly ?NeotelSystemEvent $record,
        public readonly array $payload,
        public readonly string $rawFrame,
        public readonly string $connectionId,
    ) {}
}
