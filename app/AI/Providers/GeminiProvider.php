<?php

namespace App\AI\Providers;

use App\AI\Contracts\AIProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiProvider implements AIProvider
{
    public function text(string $systemPrompt, string $userPrompt): string
    {
        $model = config('ai.gemini.model');
        $key = config('ai.gemini.key');

        $response = Http::timeout(30)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}", [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $systemPrompt . "\n\n" . $userPrompt],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Gemini request failed.');
        }

        return data_get($response->json(), 'candidates.0.content.parts.0.text')
            ?? throw new RuntimeException('Gemini returned no text.');
    }
}