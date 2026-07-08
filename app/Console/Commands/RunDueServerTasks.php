<?php

namespace App\Console\Commands;

use App\Jobs\RunServerTaskJob;
use App\Models\ServerTask;
use Illuminate\Console\Command;

class RunDueServerTasks extends Command
{
    protected $signature = 'hivepanel:run-due-tasks';

    protected $description = 'Dispatch due HivePanel server tasks.';

    public function handle(): int
    {
        $tasks = ServerTask::query()
            ->where('enabled', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->get();

        foreach ($tasks as $task) {
            RunServerTaskJob::dispatch($task->id);

            $this->info("Dispatched task {$task->id}: {$task->name}");
        }

        return self::SUCCESS;
    }
}