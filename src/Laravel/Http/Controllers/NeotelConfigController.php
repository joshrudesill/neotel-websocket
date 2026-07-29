<?php

namespace Vendor\NeotelWebsocket\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Vendor\NeotelWebsocket\Laravel\Support\NeotelSettings;
use Vendor\NeotelWebsocket\Laravel\Support\NeotelSettingsRepository;

class NeotelConfigController
{
    public function __construct(private readonly NeotelSettingsRepository $settings) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => $this->settings->all(),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function replace(Request $request): JsonResponse
    {
        $payload = (array) $request->all();

        $validator = Validator::make($payload, NeotelSettings::replacementValidationRules());
        $validator->after(function ($validator) use ($payload): void {
            $unknownKeys = array_diff(array_keys($payload), NeotelSettings::KEYS);

            if ($unknownKeys !== []) {
                $validator->errors()->add('payload', 'Unknown keys in payload: '.implode(', ', $unknownKeys));
            }
        });
        $validated = $validator->validate();

        return response()->json([
            'data' => $this->settings->replace($validated),
        ]);
    }
}
