<?php

namespace App\Support;

use Carbon\Carbon;
use Cron\CronExpression;

class ServerScheduleScheduler
{
    public static function nextRunAt(
        string $cronExpression,
        string $timezone = 'UTC',
        ?Carbon $from = null
    ): Carbon {
        $from ??= now($timezone);

        $cron = new CronExpression($cronExpression);

        return Carbon::instance(
            $cron->getNextRunDate($from, 0, true)
        )->timezone('UTC');
    }
}