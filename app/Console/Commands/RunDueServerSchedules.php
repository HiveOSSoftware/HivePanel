<?php

namespace App\Console\Commands;

use App\Jobs\RunServerScheduleJob;
use App\Models\ServerSchedule;
use Illuminate\Console\Command;

class RunDueServerSchedules extends Command
{
    protected $signature = 'hivepanel:run-due-schedules';

    protected $description = 'Dispatch all due server schedules.';

    public function handle(): int
    {
        $count = 0;

        ServerSchedule::query()
            ->where('enabled', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')
            ->chunkById(100, function ($schedules) use (&$count) {
                foreach ($schedules as $schedule) {
                    RunServerScheduleJob::dispatch($schedule->id);

                    $count++;

                    $this->line(sprintf(
                        '<info>[%s]</info> %s',
                        $schedule->id,
                        $schedule->name
                    ));
                }
            });

        if ($count === 0) {
            $this->info('No schedules are due.');
        } else {
            $this->info("Dispatched {$count} schedule(s).");
        }

        return self::SUCCESS;
    }
}