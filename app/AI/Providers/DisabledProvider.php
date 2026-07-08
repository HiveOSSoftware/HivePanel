<?php

namespace App\AI\Providers;

use App\AI\Contracts\AIProvider;
use RuntimeException;

class DisabledProvider implements AIProvider
{
    public function text(string $systemPrompt, string $userPrompt): string
    {
        throw new RuntimeException('AI is disabled. Configure an AI provider first.');
    }
}