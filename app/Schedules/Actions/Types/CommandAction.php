<?php

namespace App\Schedules\Actions\Types;

use App\Models\ServerScheduleAction;
use App\Schedules\Actions\ActionResult;
use App\Schedules\Actions\ScheduleAction;
use App\Schedules\ScheduleExecutionContext;
use App\Services\Node\CellNodeClient;

class CommandAction implements ScheduleAction
{
    public function __construct(
        private CellNodeClient $cells
    ) {}

    public function execute(ScheduleExecutionContext $context, ServerScheduleAction $action): ActionResult
    {
        $command = trim((string) ($action->payload['command'] ?? ''));

        if ($command === '') {
            return ActionResult::failure('Command is required.');
        }

        return ActionResult::success(
            $this->cells->sendCommand($context->cell, $command)
        );
    }

    public static function definition(): array
    {
        return [
            'type' => 'command',
            'name' => 'Console Command',
            'description' => 'Run a command on the server console.',
            'fields' => [
                [
                    'name' => 'command',
                    'label' => 'Command',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'say Server restarting in 5 minutes',
                ],
            ],
        ];
    }
}