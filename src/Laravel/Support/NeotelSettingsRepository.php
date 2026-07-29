<?php

namespace Vendor\NeotelWebsocket\Laravel\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Vendor\NeotelWebsocket\Laravel\Models\NeotelSetting;

class NeotelSettingsRepository
{
    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $defaults = NeotelSettings::defaults();

        if (! $this->tableExists()) {
            return $defaults;
        }

        $stored = NeotelSetting::query()
            ->whereIn('key', NeotelSettings::KEYS)
            ->pluck('value', 'key')
            ->all();

        $typed = [];

        foreach ($stored as $key => $value) {
            if (! is_string($key) || ! in_array($key, NeotelSettings::KEYS, true)) {
                continue;
            }

            $typed[$key] = NeotelSettings::decode($key, is_string($value) || $value === null ? $value : (string) $value);
        }

        return NeotelSettings::mergeWithDefaults($typed);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function replace(array $values): array
    {
        $normalized = NeotelSettings::mergeWithDefaults($values);

        if (! $this->tableExists()) {
            return $normalized;
        }

        DB::transaction(function () use ($normalized): void {
            foreach ($normalized as $key => $value) {
                NeotelSetting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => NeotelSettings::encode($key, $value)]
                );
            }
        });

        return $normalized;
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('neotel_settings');
        } catch (Throwable) {
            return false;
        }
    }
}
