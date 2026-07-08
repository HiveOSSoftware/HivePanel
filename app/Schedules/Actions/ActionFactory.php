<?php

namespace App\Schedules\Actions;

use App\Schedules\Actions\Types\BackupAction;
use App\Schedules\Actions\Types\CommandAction;
use App\Schedules\Actions\Types\RestartAction;
use App\Schedules\Actions\Types\StartAction;
use App\Schedules\Actions\Types\StopAction;
use App\Schedules\Actions\Types\UtilityAction;
use App\Schedules\Actions\Types\WaitAction;
use App\Schedules\Actions\Types\DiscordWebhookAction;
use RuntimeException;

class ActionFactory
{
    public static function make(string $type): ScheduleAction
    {
        return match ($type) {
            'command' => app(CommandAction::class),
            'backup' => app(BackupAction::class),
            'start' => app(StartAction::class),
            'stop' => app(StopAction::class),
            'restart' => app(RestartAction::class),
            'wait' => app(WaitAction::class),
            'utility' => app(UtilityAction::class),
            'discord_webhook' => app(DiscordWebhookAction::class),
            default => throw new RuntimeException("Unknown schedule action type: {$type}"),
        };
    }

    public static function definitions(): array
    {
        return [
            CommandAction::definition(),
            BackupAction::definition(),
            StartAction::definition(),
            StopAction::definition(),
            RestartAction::definition(),
            WaitAction::definition(),
            UtilityAction::definition(),
            DiscordWebhookAction::definition(),
        ];
    }
}