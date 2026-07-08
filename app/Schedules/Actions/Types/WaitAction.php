<?php

namespace App\Schedules\Actions\Types;

use App\Models\ServerScheduleAction;
use App\Schedules\Actions\ActionResult;
use App\Schedules\Actions\ScheduleAction;
use App\Schedules\ScheduleExecutionContext;

class WaitAction implements ScheduleAction
{
    public function execute(ScheduleExecutionContext $context, ServerScheduleAction $action): ActionResult
    {
        $seconds = max(1, min(3600, (int) ($action->payload['seconds'] ?? 5)));

        sleep($seconds);

        return ActionResult::success([
            'waited_seconds' => $seconds,
        ]);
    }

    public static function definition(): array
    {
        return [
            'type' => 'wait',
            'name' => 'Wait',
            'description' => 'Pause before running the next action.',
            'fields' => [
                [
                    'name' => 'seconds',
                    'label' => 'Wait time',
                    'type' => 'number',
                    'required' => true,
                    'default' => 5,
                    'suffix' => 'seconds',
                ],
            ],
        ];
    }
}