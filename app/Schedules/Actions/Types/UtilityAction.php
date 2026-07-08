<?php

namespace App\Schedules\Actions\Types;

use App\Models\ServerScheduleAction;
use App\Schedules\Actions\ActionResult;
use App\Schedules\Actions\ScheduleAction;
use App\Schedules\ScheduleExecutionContext;
use App\Services\Node\CellNodeClient;

class UtilityAction implements ScheduleAction
{
    public function __construct(
        private CellNodeClient $cells
    ) {}

    public function execute(ScheduleExecutionContext $context, ServerScheduleAction $action): ActionResult
    {
        $utility = (string) ($action->payload['utility'] ?? '');

        if ($utility === '') {
            return ActionResult::failure('Utility is required.');
        }

        return ActionResult::success(
            $this->cells->runUtility($context->cell, $utility)
        );
    }

    public static function definition(): array
    {
        return [
            'type' => 'utility',
            'name' => 'Run Utility',
            'description' => 'Run a server utility script.',
            'fields' => [
                [
                    'name' => 'utility',
                    'label' => 'Utility',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['label' => 'Reset files', 'value' => 'reset-files'],
                        ['label' => 'Reset world', 'value' => 'reset-world'],
                        ['label' => 'Repair permissions', 'value' => 'repair-permissions'],
                        ['label' => 'Clear logs', 'value' => 'clear-logs'],
                    ],
                ],
            ],
        ];
    }
}