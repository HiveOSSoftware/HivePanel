<?php

namespace App\Jobs;

use App\Models\Cell;
use App\Services\Cells\CellSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ReconcileCellJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 30;
    public int $uniqueFor = 240;

    public function __construct(public string $cellId) {
    }

    public function uniqueId(): string
    {
        return $this->cellId;
    }

    public function handle(CellSyncService $sync): void
    {
        $cell = Cell::query()
            ->with(['node', 'allocation'])
            ->find($this->cellId);

        if (! $cell) {
            return;
        }

        if ($cell->install_status?->value !== 'installed') {
            return;
        }

        if (! $cell->node || ! $cell->daemon_id) {
            return;
        }

        try {
            $sync->inspect($cell);
        } catch (Throwable $exception) {
            report($exception);

            $cell->forceFill([
                'worker_sync_status' => 'error',
                'worker_sync_message' => $exception->getMessage() ?: 'Unable to inspect the Worker cell.',
                'worker_sync_differences' => [],
                'worker_sync_checked_at' => now(),
            ])->save();
        }
    }
}