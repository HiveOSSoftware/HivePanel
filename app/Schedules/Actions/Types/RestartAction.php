<?php

namespace App\Schedules\Actions\Types;

use App\Models\ServerScheduleAction;
use App\Schedules\Actions\ActionResult;
use App\Schedules\Actions\ScheduleAction;
use App\Schedules\ScheduleExecutionContext;
use App\Services\Node\CellNodeClient;

class RestartAction implements ScheduleAction
{
    public function __construct(
        private CellNodeClient $cells
    ) {}

    public function execute(ScheduleExecutionContext $context, ServerScheduleAction $action): ActionResult
    {
        $this->cells->stopCell($context->cell);

        sleep((int) ($action->payload['delay'] ?? 5));

        return ActionResult::success(
            $this->cells->startCell($context->cell)
        );
    }

    public static function definition(): array
    {
        return [
            'type' => 'restart',
            'name' => 'Restart Server',
            'description' => 'Stop and start the server.',
            'fields' => [
                [
                    'name' => 'delay',
                    'label' => 'Delay before start',
                    'type' => 'number',
                    'required' => false,
                    'default' => 5,
                    'suffix' => 'seconds',
                ],
            ],
        ];
    }
}