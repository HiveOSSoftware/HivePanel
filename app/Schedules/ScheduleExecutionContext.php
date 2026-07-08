<?php

namespace App\Schedules;

use App\Models\Cell;
use App\Models\ServerSchedule;

class ScheduleExecutionContext
{
    public function __construct(
        public ServerSchedule $schedule,
        public Cell $cell,
    ) {}

    public static function fromSchedule(ServerSchedule $schedule): self
    {
        return new self(
            schedule: $schedule,
            cell: Cell::query()
                ->with('node')
                ->findOrFail($schedule->cell_id),
        );
    }
}