<?php

namespace App\Jobs;

use App\Models\Cell;
use App\Services\Node\CellNodeClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class InstallCellJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 900;

    public function __construct(
        public int|string $cellId,
        public bool $startAfterInstall = false,
    ) {}

    public function handle(CellNodeClient $cells): void
    {
        $cell = Cell::query()
            ->with('node')
            ->findOrFail($this->cellId);

        if (! $cell->daemon_id || ! $cell->node) {
            return;
        }

        $cell->update([
            'metadata->install_status' => 'running',
            'metadata->install_started_at' => now()->toISOString(),
        ]);

        try {
            $cells->installCell($cell);

            $cell->update([
                'metadata->install_status' => 'completed',
                'metadata->install_completed_at' => now()->toISOString(),
            ]);

            if ($this->startAfterInstall) {
                $cells->startCell($cell);

                $cell->update([
                    'metadata->started_after_install' => true,
                ]);
            }
        } catch (Throwable $e) {
            $cell->update([
                'metadata->install_status' => 'failed',
                'metadata->install_error' => $e->getMessage(),
                'metadata->install_failed_at' => now()->toISOString(),
            ]);

            throw $e;
        }
    }
}