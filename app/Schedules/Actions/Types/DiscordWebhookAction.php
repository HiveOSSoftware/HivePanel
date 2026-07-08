<?php

namespace App\Schedules\Actions\Types;

use App\Models\ServerScheduleAction;
use App\Schedules\Actions\ActionResult;
use App\Schedules\Actions\ScheduleAction;
use App\Schedules\ScheduleExecutionContext;
use Illuminate\Support\Facades\Http;
use Throwable;

class DiscordWebhookAction implements ScheduleAction
{
    public function execute(ScheduleExecutionContext $context, ServerScheduleAction $action): ActionResult
    {
        $url = trim((string) ($action->payload['url'] ?? ''));
        $message = trim((string) ($action->payload['message'] ?? ''));

        if ($url === '') {
            return ActionResult::failure('Webhook URL is required.');
        }

        if ($message === '') {
            return ActionResult::failure('Webhook message is required.');
        }

        try {
            Http::timeout(10)
                ->post($url, [
                    'content' => $message,
                ])
                ->throw();

            return ActionResult::success('Discord webhook sent successfully.');
        } catch (Throwable $e) {
            return ActionResult::failure($e->getMessage());
        }
    }

    public static function definition(): array
    {
        return [
            'type' => 'discord_webhook',
            'name' => 'Discord Webhook',
            'description' => 'Send a message to a Discord webhook.',
            'fields' => [
                [
                    'name' => 'url',
                    'label' => 'Webhook URL',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'https://discord.com/api/webhooks/...',
                ],
                [
                    'name' => 'message',
                    'label' => 'Message',
                    'type' => 'textarea',
                    'required' => true,
                    'placeholder' => 'Server restart completed.',
                ],
            ],
        ];
    }
}