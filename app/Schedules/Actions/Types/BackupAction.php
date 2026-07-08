<?php

namespace App\Schedules\Actions\Types;

use App\Models\ServerScheduleAction;
use App\Schedules\Actions\ActionResult;
use App\Schedules\Actions\ScheduleAction;
use App\Schedules\ScheduleExecutionContext;
use App\Services\Node\BackupNodeClient;

class BackupAction implements ScheduleAction
{
    public function __construct(
        private BackupNodeClient $backups
    ) {}

    public function execute(ScheduleExecutionContext $context, ServerScheduleAction $action): ActionResult
    {
        return ActionResult::success(
            $this->backups->createBackup($context->cell)
        );
    }

    public static function definition(): array
    {
        return [
            'type' => 'backup',
            'name' => 'Create Backup',
            'description' => 'Create a server backup.',
            'fields' => [],
        ];
    }
}