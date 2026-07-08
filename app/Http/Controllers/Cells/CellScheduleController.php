<?php

namespace App\Http\Controllers\Cells;

use App\AI\Features\GenerateCron;
use App\AI\Features\GenerateWorkflow;
use App\Enums\AuditEvent;
use App\Jobs\RunServerScheduleJob;
use App\Models\ServerSchedule;
use App\Schedules\Actions\ActionFactory;
use App\Schedules\Templates\ScheduleTemplateRegistry;
use App\Services\AuditLogger;
use App\Services\Node\CellNodeClient;
use App\Support\ServerScheduleScheduler;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CellScheduleController extends CellBaseController
{
    public function actionDefinitions()
    {
        return response()->json([
            'actions' => ActionFactory::definitions(),
        ]);
    }

    public function index(string $id, CellNodeClient $cells)
    {
        $cell = $this->panelCellOrFail($id);
        $workerCell = $this->getCellOrFail($cell, $cells);

        return Inertia::render('Cells/Schedules', [
            'cell' => $workerCell,
            'schedules' => $this->scheduleQuery($cell->id)->get(),
        ]);
    }

    public function show(string $id, int|string $scheduleId, CellNodeClient $cells)
    {
        $cell = $this->panelCellOrFail($id);
        $workerCell = $this->getCellOrFail($cell, $cells);

        $schedule = $this->scheduleForCellOrFail($cell->id, $scheduleId)
            ->load([
                'actions',
                'runs' => fn ($query) => $query
                    ->with('actionLogs')
                    ->latest()
                    ->limit(20),
            ]);

        return Inertia::render('Cells/Schedules/Show', [
            'cell' => $workerCell,
            'schedule' => $schedule,
            'actionDefinitions' => ActionFactory::definitions(),
        ]);
    }

    public function store(string $id, Request $request, AuditLogger $audit)
    {
        $cell = $this->panelCellOrFail($id);
        $data = $this->validated($request);

        $schedule = ServerSchedule::create([
            ...$data,
            'cell_id' => $cell->id,
            'enabled' => $data['enabled'] ?? true,
            'only_when_online' => $data['only_when_online'] ?? false,
            'continue_on_failure' => $data['continue_on_failure'] ?? false,
            'next_run_at' => ServerScheduleScheduler::nextRunAt(
                $data['cron_expression'],
                $data['timezone'] ?? 'UTC'
            ),
        ]);

        $this->syncActions($schedule, $data['actions'] ?? []);

        $audit->log(
            AuditEvent::SCHEDULE_CREATED,
            $cell,
            "Schedule \"{$schedule->name}\" was created.",
            ['schedule_id' => $schedule->id]
        );

        return response()->json(
            $this->freshSchedule($schedule)
        );
    }

    public function update(string $id, int|string $scheduleId, Request $request, AuditLogger $audit)
    {
        $cell = $this->panelCellOrFail($id);
        $schedule = $this->scheduleForCellOrFail($cell->id, $scheduleId);

        $wasEnabled = (bool) $schedule->enabled;
        $data = $this->validated($request);

        $schedule->update([
            ...$data,
            'enabled' => $data['enabled'] ?? false,
            'only_when_online' => $data['only_when_online'] ?? false,
            'continue_on_failure' => $data['continue_on_failure'] ?? false,
            'next_run_at' => ServerScheduleScheduler::nextRunAt(
                $data['cron_expression'],
                $data['timezone'] ?? 'UTC'
            ),
        ]);

        $this->syncActions($schedule, $data['actions'] ?? []);

        $event = AuditEvent::SCHEDULE_UPDATED;

        if ($wasEnabled !== (bool) $schedule->enabled) {
            $event = $schedule->enabled
                ? AuditEvent::SCHEDULE_ENABLED
                : AuditEvent::SCHEDULE_DISABLED;
        }

        $audit->log(
            $event,
            $cell,
            "Schedule \"{$schedule->name}\" was updated.",
            ['schedule_id' => $schedule->id]
        );

        return response()->json(
            $this->freshSchedule($schedule)
        );
    }

    public function destroy(string $id, int|string $scheduleId, AuditLogger $audit)
    {
        $cell = $this->panelCellOrFail($id);
        $schedule = $this->scheduleForCellOrFail($cell->id, $scheduleId);

        $name = $schedule->name;
        $schedule->delete();

        $audit->log(
            AuditEvent::SCHEDULE_DELETED,
            $cell,
            "Schedule \"{$name}\" was deleted.",
            ['schedule_id' => $scheduleId]
        );

        return response()->json([
            'message' => 'schedule deleted',
        ]);
    }

    public function run(string $id, int|string $scheduleId, AuditLogger $audit)
    {
        $cell = $this->panelCellOrFail($id);
        $schedule = $this->scheduleForCellOrFail($cell->id, $scheduleId);

        RunServerScheduleJob::dispatch($schedule->id);

        $audit->log(
            AuditEvent::SCHEDULE_EXECUTED,
            $cell,
            "Schedule \"{$schedule->name}\" was manually dispatched.",
            ['schedule_id' => $schedule->id]
        );

        return response()->json([
            'message' => 'schedule dispatched',
        ]);
    }

    public function json(string $id)
    {
        $cell = $this->panelCellOrFail($id);

        return response()->json([
            'schedules' => $this->scheduleQuery($cell->id)->get(),
        ]);
    }

    public function templates(string $id)
    {
        $cell = $this->panelCellOrFail($id);

        $comb = $cell->comb;

        return response()->json([
            'comb' => [
                'name' => $comb,
                'slug' => $comb,
            ],
            'templates' => ScheduleTemplateRegistry::forComb($comb),
        ]);
    }

    public function generateCron(Request $request, GenerateCron $generator)
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:500'],
            'timezone' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json(
            $generator->handle(
                $data['prompt'],
                $data['timezone'] ?? 'UTC'
            )
        );
    }

    public function generateWorkflow(Request $request, GenerateWorkflow $generator)
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:1000'],
        ]);

        return response()->json(
            $generator->handle($data['prompt'])
        );
    }

    private function scheduleQuery(string $cellId)
    {
        return ServerSchedule::query()
            ->where('cell_id', $cellId)
            ->with([
                'actions',
                'runs' => fn ($query) => $query
                    ->with('actionLogs')
                    ->latest()
                    ->limit(5),
            ])
            ->latest();
    }

    private function freshSchedule(ServerSchedule $schedule): ServerSchedule
    {
        return $schedule->fresh()->load([
            'actions',
            'runs' => fn ($query) => $query
                ->with('actionLogs')
                ->latest()
                ->limit(5),
        ]);
    }

    private function scheduleForCellOrFail(string $cellId, int|string $scheduleId): ServerSchedule
    {
        return ServerSchedule::query()
            ->where('id', $scheduleId)
            ->where('cell_id', $cellId)
            ->firstOrFail();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cron_expression' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'max:100'],
            'enabled' => ['boolean'],
            'only_when_online' => ['boolean'],
            'continue_on_failure' => ['boolean'],
            'actions' => ['required', 'array', 'min:1'],
            'actions.*.type' => ['required', 'string', 'in:command,backup,start,stop,restart,wait,utility,discord_webhook'],
            'actions.*.payload' => ['nullable', 'array'],
        ]);
    }

    private function syncActions(ServerSchedule $schedule, array $actions): void
    {
        $schedule->actions()->delete();

        foreach ($actions as $index => $action) {
            $schedule->actions()->create([
                'sort_order' => $index,
                'type' => $action['type'],
                'payload' => $action['payload'] ?? [],
            ]);
        }
    }
}