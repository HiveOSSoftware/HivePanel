<?php

namespace App\Schedules\Actions\Types;

use App\Models\ServerScheduleAction;
use App\Schedules\Actions\ActionResult;
use App\Schedules\Actions\ScheduleAction;
use App\Schedules\ScheduleExecutionContext;
use App\Services\Node\CellNodeClient;

class StopAction implements ScheduleAction
{
    public function __construct(
        private CellNodeClient $cells
    ) {}

    public function execute(ScheduleExecutionContext $context, ServerScheduleAction $action): ActionResult
    {
        return ActionResult::success(
            $this->cells->stopCell($context->cell)
        );
    }

    public static function definition(): array
    {
        return [
            'type' => 'stop',
            'name' => 'Stop Server',
            'description' => 'Stop the server.',
            'fields' => [],
        ];
    }
}