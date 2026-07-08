<?php

namespace App\AI;

use App\AI\Contracts\AIProvider;
use App\AI\Providers\DisabledProvider;
use App\AI\Providers\GeminiProvider;
use App\AI\Providers\OllamaProvider;
use App\AI\Providers\OpenAIProvider;
use App\AI\Providers\OpenRouterProvider;
use RuntimeException;

class AIManager
{
    public function provider(): AIProvider
    {
        return match (config('ai.provider')) {
            'openai' => app(OpenAIProvider::class),
            'openrouter' => app(OpenRouterProvider::class),
            'gemini' => app(GeminiProvider::class),
            'ollama' => app(OllamaProvider::class),
            'disabled' => app(DisabledProvider::class),
            default => throw new RuntimeException('Invalid AI provider configured.'),
        };
    }

    public function text(string $systemPrompt, string $userPrompt): string
    {
        return $this->provider()->text($systemPrompt, $userPrompt);
    }
}