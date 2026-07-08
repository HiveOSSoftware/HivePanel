<?php

namespace App\AI\Features;

use App\AI\AIManager;
use Illuminate\Validation\ValidationException;

class GenerateCron
{
    public function __construct(
        private AIManager $ai
    ) {}

    public function handle(string $prompt, string $timezone = 'UTC'): array
    {
        $text = $this->ai->text(
            systemPrompt: 'You convert natural language schedules into standard 5-field cron expressions. Return only valid JSON with keys: cron, description. Do not include seconds. Do not include markdown.',
            userPrompt: sprintf(
                'Timezone: %s. Schedule request: %s',
                $timezone,
                $prompt
            )
        );

        $json = json_decode(trim($text), true);

        if (! is_array($json) || empty($json['cron'])) {
            throw ValidationException::withMessages([
                'prompt' => 'AI returned an invalid cron expression.',
            ]);
        }

        return [
            'cron' => $json['cron'],
            'description' => $json['description'] ?? null,
        ];
    }
}