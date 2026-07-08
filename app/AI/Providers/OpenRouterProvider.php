<?php

namespace App\AI\Providers;

use App\AI\Contracts\AIProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenRouterProvider implements AIProvider
{
    public function text(string $systemPrompt, string $userPrompt): string
    {
        $response = Http::withToken(config('ai.openrouter.key'))
            ->timeout(30)
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => config('ai.openrouter.model'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenRouter request failed.');
        }

        return data_get($response->json(), 'choices.0.message.content')
            ?? throw new RuntimeException('OpenRouter returned no text.');
    }
}