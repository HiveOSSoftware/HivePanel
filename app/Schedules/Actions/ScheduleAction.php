<?php

namespace App\Schedules\Actions;

use App\Models\ServerScheduleAction;
use App\Schedules\ScheduleExecutionContext;

interface ScheduleAction
{
    public function execute(
        ScheduleExecutionContext $context,
        ServerScheduleAction $action
    ): ActionResult;

    public static function definition(): array;
}