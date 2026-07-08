<?php

namespace App\Schedules\Templates;

class MinecraftScheduleTemplates
{
    public static function templates(): array
    {
        return [
            [
                'id' => 'minecraft_restart_backup',
                'name' => 'Restart + Backup',
                'description' => 'Warn players, wait 5 minutes, backup, restart, then announce online.',
                'cron_expression' => '0 3 * * *',
                'timezone' => 'Europe/London',
                'enabled' => true,
                'only_when_online' => false,
                'continue_on_failure' => false,
                'actions' => [
                    [
                        'type' => 'command',
                        'payload' => [
                            'command' => 'say Server restarting in 5 minutes.',
                        ],
                    ],
                    [
                        'type' => 'wait',
                        'payload' => [
                            'seconds' => 300,
                        ],
                    ],
                    [
                        'type' => 'backup',
                        'payload' => [],
                    ],
                    [
                        'type' => 'restart',
                        'payload' => [],
                    ],
                    [
                        'type' => 'command',
                        'payload' => [
                            'command' => 'say Server is back online.',
                        ],
                    ],
                ],
            ],
        ];
    }
}