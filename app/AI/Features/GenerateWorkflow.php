<?php

namespace App\AI\Features;

use App\AI\AIManager;
use App\Schedules\Actions\ActionFactory;
use Illuminate\Support\Arr;

class GenerateWorkflow
{
    public function __construct(
        private AIManager $ai
    ) {}

    public function handle(string $prompt): array
    {
        $definitions = ActionFactory::definitions();

        $systemPrompt = <<<PROMPT
You are HivePanel AI.

You generate game server automation workflows.

You MUST return ONLY valid JSON.
Never wrap the JSON in markdown.
Never explain anything.

The format MUST be:

{
    "actions": [
        {
            "type": "command",
            "payload": {
                "command": "say Restarting..."
            }
        }
    ]
}

Available action definitions:

{$this->definitionsForPrompt($definitions)}

Rules:
- Use ONLY available action types.
- Never invent action types.
- Use payload fields exactly as defined.
- Warning players should use command actions.
- If waiting 5 minutes, use seconds: 300.
- If waiting 1 minute, use seconds: 60.
- Restarts should use restart.
- Backups should use backup.
- Starts should use start.
- Stops should use stop.
- Utility tasks should use utility.
- Think like an experienced game server administrator.
- Discord notifications should use discord_webhook.
- Only use discord_webhook if the user mentions Discord/webhook/notification.
- If no webhook URL is provided, leave url as an empty string.
- Always generate sensible, safe workflows.

Example user prompt:
Restart every night. Warn players 5 minutes before. Take a backup.

Example output:
{
    "actions": [
        {
            "type": "command",
            "payload": {
                "command": "say Server restarting in 5 minutes."
            }
        },
        {
            "type": "wait",
            "payload": {
                "seconds": 300
            }
        },
        {
            "type": "backup",
            "payload": {}
        },
        {
            "type": "restart",
            "payload": {}
        },
        {
            "type": "command",
            "payload": {
                "command": "say Server is back online."
            }
        }
    ]
}

Example user prompt:
Restart the server and send a Discord notification when done.

Example output:
{
    "actions": [
        {
            "type": "restart",
            "payload": {}
        },
        {
            "type": "discord_webhook",
            "payload": {
                "url": "",
                "message": "Server restart completed."
            }
        }
    ]
}
PROMPT;

        $response = $this->ai->text($systemPrompt, $prompt);

        $json = $this->extractJson($response);

        return [
            'actions' => $this->sanitizeActions(
                $json['actions'] ?? [],
                $definitions
            ),
        ];
    }

    private function definitionsForPrompt(array $definitions): string
    {
        return collect($definitions)
            ->map(function (array $definition) {
                return [
                    'type' => $definition['type'],
                    'name' => $definition['name'],
                    'description' => $definition['description'] ?? '',
                    'fields' => collect($definition['fields'] ?? [])
                        ->map(fn (array $field) => [
                            'name' => $field['name'],
                            'label' => $field['label'] ?? $field['name'],
                            'type' => $field['type'],
                            'required' => $field['required'] ?? false,
                            'default' => $field['default'] ?? null,
                            'options' => $field['options'] ?? [],
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->toJson(JSON_PRETTY_PRINT);
    }

    private function extractJson(string $response): array
    {
        $response = trim($response);

        if (str_starts_with($response, '```')) {
            $response = preg_replace('/^```(?:json)?/i', '', $response);
            $response = preg_replace('/```$/', '', $response);
            $response = trim($response);
        }

        $decoded = json_decode($response, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        preg_match('/\{.*\}/s', $response, $matches);

        if (! empty($matches[0])) {
            $decoded = json_decode($matches[0], true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [
            'actions' => [
                [
                    'type' => 'command',
                    'payload' => [
                        'command' => 'say Scheduled workflow running...',
                    ],
                ],
            ],
        ];
    }

    private function sanitizeActions(array $actions, array $definitions): array
    {
        $definitionsByType = collect($definitions)->keyBy('type');

        $sanitized = collect($actions)
            ->filter(fn ($action) => is_array($action))
            ->map(function (array $action) use ($definitionsByType) {
                $type = $action['type'] ?? null;

                if (! $type || ! $definitionsByType->has($type)) {
                    return null;
                }

                $definition = $definitionsByType->get($type);

                $allowedFields = collect($definition['fields'] ?? [])
                    ->pluck('name')
                    ->all();

                $payload = Arr::only($action['payload'] ?? [], $allowedFields);

                foreach ($definition['fields'] ?? [] as $field) {
                    if (! array_key_exists($field['name'], $payload)) {
                        $payload[$field['name']] = $field['default'] ?? '';
                    }
                }

                return [
                    'type' => $type,
                    'payload' => $payload,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if (empty($sanitized)) {
            return [
                [
                    'type' => 'command',
                    'payload' => [
                        'command' => 'say Scheduled workflow running...',
                    ],
                ],
            ];
        }

        return $sanitized;
    }
}