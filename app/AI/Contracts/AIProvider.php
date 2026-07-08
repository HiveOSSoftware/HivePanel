<?php

namespace App\AI\Contracts;

interface AIProvider
{
    public function text(string $systemPrompt, string $userPrompt): string;
}