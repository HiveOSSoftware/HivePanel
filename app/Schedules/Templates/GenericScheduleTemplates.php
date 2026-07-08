<?php

namespace App\Schedules\Templates;

class GenericScheduleTemplates
{
    public static function templates(): array
    {
        return [
            [
                'id' => 'custom',
                'name' => 'Custom Schedule',
                'description' => 'Start with a blank workflow.',
                'cron_expression' => '0 3 * * *',
                'timezone' => 'Europe/London',
                'enabled' => true,
                'only_when_online' => false,
                'continue_on_failure' => false,
                'actions' => [
                    [
                        'type' => 'command',
                        'payload' => [
                            'command' => '',
                        ],
                    ],
                ],
            ],
            [
                'id' => 'backup_only',
                'name' => 'Backup Only',
                'description' => 'Take a backup on a schedule.',
                'cron_expression' => '0 3 * * *',
                'timezone' => 'Europe/London',
                'enabled' => true,
                'only_when_online' => false,
                'continue_on_failure' => false,
                'actions' => [
                    [
                        'type' => 'backup',
                        'payload' => [],
                    ],
                ],
            ],
        ];
    }
}