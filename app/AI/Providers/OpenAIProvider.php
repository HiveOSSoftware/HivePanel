<?php

namespace App\AI\Providers;

use App\AI\Contracts\AIProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAIProvider implements AIProvider
{
    public function text(string $systemPrompt, string $userPrompt): string
    {
        $response = Http::withToken(config('ai.openai.key'))
            ->timeout(30)
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('ai.openai.model'),
                'input' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI request failed.');
        }

        return data_get($response->json(), 'output.0.content.0.text')
            ?? throw new RuntimeException('OpenAI returned no text.');
    }
}