<?php

namespace Vendor\NeotelWebsocket\Laravel\Models;

use Illuminate\Database\Eloquent\Model;

class NeotelSetting extends Model
{
    protected $table = 'neotel_settings';

    protected $fillable = [
        'key',
        'value',
    ];
}
