<?php

namespace Vendor\NeotelWebsocket\Laravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NeotelCallEvent extends Model
{
    use HasFactory;

    protected $table = 'neotel_call_events';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'connection_id',
        'callid',
        'action',
        'server_name',
        'extension',
        'state_code',
        'state_desc',
        'cause',
        'payload',
        'raw_payload',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            if (! filled($event->getKey())) {
                $event->{$event->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
