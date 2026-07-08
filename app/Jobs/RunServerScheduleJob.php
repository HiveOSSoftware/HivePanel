<?php

namespace App\Jobs;

use App\Models\ServerSchedule;
use App\Models\ServerScheduleRun;
use App\Schedules\Actions\ActionFactory;
use App\Schedules\ScheduleExecutionContext;
use App\Support\ServerScheduleScheduler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunServerScheduleJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $scheduleId
    ) {}

    public function handle(): void
    {
        $schedule = ServerSchedule::query()
            ->with(['actions' => fn ($query) => $query->orderBy('sort_order')])
            ->findOrFail($this->scheduleId);

        if (! $schedule->enabled) {
            return;
        }

        $context = ScheduleExecutionContext::fromSchedule($schedule);

        $run = ServerScheduleRun::create([
            'server_schedule_id' => $schedule->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $outputs = [];

        try {
            foreach ($schedule->actions as $action) {
                $actionLog = $run->actionLogs()->create([
                    'server_schedule_action_id' => $action->id,
                    'sort_order' => $action->sort_order,
                    'type' => $action->type,
                    'status' => 'running',
                    'payload' => $action->payload ?? [],
                    'started_at' => now(),
                ]);

                try {
                    $handler = ActionFactory::make($action->type);

                    $result = $handler->execute($context, $action);

                    $status = $result->success ? 'success' : 'failed';

                    $outputs[] = [
                        'action_id' => $action->id,
                        'type' => $action->type,
                        'success' => $result->success,
                        'output' => $result->output,
                        'error' => $result->error,
                    ];

                    $actionLog->update([
                        'status' => $status,
                        'result' => [
                            'success' => $result->success,
                            'output' => $result->output,
                            'error' => $result->error,
                        ],
                        'error' => $result->error,
                        'finished_at' => now(),
                    ]);

                    if (! $result->success && ! $schedule->continue_on_failure) {
                        $run->update([
                            'status' => 'failed',
                            'output' => json_encode($outputs, JSON_PRETTY_PRINT),
                            'error' => $result->error,
                            'finished_at' => now(),
                        ]);

                        $schedule->update([
                            'last_run_at' => now(),
                        ]);

                        $this->updateNextRun($schedule);

                        return;
                    }
                } catch (Throwable $e) {
                    $outputs[] = [
                        'action_id' => $action->id,
                        'type' => $action->type,
                        'success' => false,
                        'output' => null,
                        'error' => $e->getMessage(),
                    ];

                    $actionLog->update([
                        'status' => 'failed',
                        'result' => [
                            'success' => false,
                            'output' => null,
                            'error' => $e->getMessage(),
                        ],
                        'error' => $e->getMessage(),
                        'finished_at' => now(),
                    ]);

                    if (! $schedule->continue_on_failure) {
                        $run->update([
                            'status' => 'failed',
                            'output' => json_encode($outputs, JSON_PRETTY_PRINT),
                            'error' => $e->getMessage(),
                            'finished_at' => now(),
                        ]);

                        $schedule->update([
                            'last_run_at' => now(),
                        ]);

                        $this->updateNextRun($schedule);

                        throw $e;
                    }
                }
            }

            $run->update([
                'status' => 'success',
                'output' => json_encode($outputs, JSON_PRETTY_PRINT),
                'finished_at' => now(),
            ]);

            $schedule->update([
                'last_run_at' => now(),
            ]);

            $this->updateNextRun($schedule);
        } catch (Throwable $e) {
            if ($run->status !== 'failed') {
                $run->update([
                    'status' => 'failed',
                    'output' => json_encode($outputs, JSON_PRETTY_PRINT),
                    'error' => $e->getMessage(),
                    'finished_at' => now(),
                ]);
            }

            $schedule->update([
                'last_run_at' => now(),
            ]);

            $this->updateNextRun($schedule);

            throw $e;
        }
    }

    private function updateNextRun(ServerSchedule $schedule): void
    {
        $schedule->update([
            'next_run_at' => ServerScheduleScheduler::nextRunAt(
                $schedule->cron_expression,
                $schedule->timezone ?? 'UTC',
                now($schedule->timezone ?? 'UTC')
            ),
        ]);
    }
}