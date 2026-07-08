<?php

namespace App\Schedules\Actions\Types;

use App\Models\ServerScheduleAction;
use App\Schedules\Actions\ActionResult;
use App\Schedules\Actions\ScheduleAction;
use App\Schedules\ScheduleExecutionContext;
use App\Services\Node\CellNodeClient;

class StartAction implements ScheduleAction
{
    public function __construct(
        private CellNodeClient $cells
    ) {}

    public function execute(ScheduleExecutionContext $context, ServerScheduleAction $action): ActionResult
    {
        return ActionResult::success(
            $this->cells->startCell($context->cell)
        );
    }

    public static function definition(): array
    {
        return [
            'type' => 'start',
            'name' => 'Start Server',
            'description' => 'Start the server.',
            'fields' => [],
        ];
    }
}