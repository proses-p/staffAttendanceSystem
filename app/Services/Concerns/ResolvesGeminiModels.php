<?php

namespace App\Services\Concerns;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

trait ResolvesGeminiModels
{
    private function resolveAvailableModels(string $apiKey): array
    {
        return Cache::remember('gemini_active_models', 43200, function () use ($apiKey): array {
            try {
                $response = Http::acceptJson()
                    ->timeout(15)
                    ->get('https://generativelanguage.googleapis.com/v1beta/models', [
                        'key' => $apiKey,
                    ]);
            } catch (ConnectionException) {
                return ['gemini-3.5-flash-lite', 'gemini-3.8-flash', 'gemini-2.0-flash'];
            }

            if (! $response->successful()) {
                return ['gemini-3.5-flash-lite', 'gemini-3.8-flash', 'gemini-2.0-flash'];
            }

            $models = $response->json('models', []);
            if (! is_array($models)) {
                return ['gemini-3.5-flash-lite', 'gemini-3.8-flash', 'gemini-2.0-flash'];
            }

            $availableModels = [];
            foreach ($models as $model) {
                if (! is_array($model)
                    || ! is_string($model['name'] ?? null)
                    || ! is_array($model['supportedGenerationMethods'] ?? null)
                    || ! in_array('generateContent', $model['supportedGenerationMethods'], true)) {
                    continue;
                }

                $name = preg_replace('#^models/#', '', $model['name']);
                $lowerName = strtolower($name);
                if (! str_contains($lowerName, 'flash')
                    || preg_match('/(?:^|[-_])(?:image|tts|audio|exp)(?:[-_]|$)/', $lowerName)) {
                    continue;
                }

                $availableModels[] = $name;
            }

            if ($availableModels === []) {
                return ['gemini-3.5-flash-lite', 'gemini-3.8-flash', 'gemini-2.0-flash'];
            }

            usort(
                $availableModels,
                static fn (string $left, string $right): int =>
                    (int) str_contains(strtolower($right), '-lite')
                    <=> (int) str_contains(strtolower($left), '-lite')
            );

            return array_values(array_unique($availableModels));
        });
    }
}
