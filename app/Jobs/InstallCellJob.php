<?php

namespace App\Jobs;

use App\Enums\CellInstallStatus;
use App\Models\Cell;
use App\Services\Node\CellNodeClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class InstallCellJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public int|string $cellId,
        public bool $startAfterInstall = false,
    ) {
    }

    public function handle(CellNodeClient $cells): void
    {
        $cell = Cell::query()
            ->with('node')
            ->findOrFail($this->cellId);

        try {
            if (! $cell->daemon_id) {
                throw new RuntimeException(
                    'The cell does not have a daemon ID.',
                );
            }

            if (! $cell->node) {
                throw new RuntimeException(
                    'The cell is not assigned to a valid node.',
                );
            }

            $cell->forceFill([
                'install_status' => CellInstallStatus::INSTALLING,
                'install_failure_reason' => null,
                'installed_at' => null,
            ])->save();

            $cells->installCell($cell);

            $cell->forceFill([
                'install_status' => CellInstallStatus::INSTALLED,
                'install_failure_reason' => null,
                'installed_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $cell->forceFill([
                'install_status' => CellInstallStatus::FAILED,
                'install_failure_reason' => $this->failureMessage(
                    $exception,
                ),
                'installed_at' => null,
            ])->save();

            throw $exception;
        }

        /*
         * Starting the cell is separate from installation.
         *
         * If starting fails, the software was still installed successfully,
         * so do not change install_status back to failed.
         */
        if ($this->startAfterInstall) {
            $cells->startCell($cell);

            $metadata = $cell->metadata ?? [];

            $metadata['started_after_install'] = true;
            $metadata['started_after_install_at'] = now()->toISOString();

            $cell->forceFill([
                'metadata' => $metadata,
            ])->save();
        }
    }

    private function failureMessage(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        if ($message === '') {
            $message = 'The cell installation failed unexpectedly.';
        }

        return mb_substr($message, 0, 5000);
    }
}