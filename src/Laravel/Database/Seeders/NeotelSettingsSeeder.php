<?php

namespace Vendor\NeotelWebsocket\Laravel\Database\Seeders;

use Illuminate\Database\Seeder;
use Vendor\NeotelWebsocket\Laravel\Support\NeotelSettingsRepository;

class NeotelSettingsSeeder extends Seeder
{
    public function run(): void
    {
        app(NeotelSettingsRepository::class)->replace([]);
    }
}
