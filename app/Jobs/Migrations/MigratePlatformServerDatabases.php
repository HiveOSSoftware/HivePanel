<?php

namespace App\Jobs\Migrations;

use App\Models\PlatformMigrationServer;
use App\Services\Migrations\MigrationDatabaseTransferService;
use App\Services\Migrations\MigrationExecutionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class MigratePlatformServerDatabases implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 7200;

    public function __construct(
        public readonly string $migrationServerId,
    ) {
    }

    public function handle(
        MigrationDatabaseTransferService $databases,
        MigrationExecutionService $execution,
    ): void {
        $server = PlatformMigrationServer::query()
            ->with('migration')
            ->find($this->migrationServerId);

        if (! $server) {
            return;
        }

        if (! in_array(
            $server->status,
            [
                'database_pending',
                'database_failed',
                'database_transferring',
            ],
            true
        )) {
            $execution->refreshMigrationStatus(
                $server->migration,
            );

            return;
        }

        $lock = Cache::lock(
            'platform-migration-databases:'
            . $server->id,
            7200,
        );

        if (! $lock->get()) {
            return;
        }

        try {
            $server->refresh();

            if (! in_array(
                $server->status,
                [
                    'database_pending',
                    'database_failed',
                ],
                true
            )) {
                return;
            }

            $databases->transferServer(
                $server,
            );
        } catch (Throwable $exception) {
            report($exception);

            $server->forceFill([
                'status' => 'database_failed',
                'current_stage' => 'Database migration failed',
                'progress' => max(
                    85,
                    (int) $server->progress,
                ),
                'error' => $exception->getMessage()
                    ?: 'Database migration failed.',
                'completed_at' => null,
            ])->save();
        } finally {
            $lock->release();

            $execution->refreshMigrationStatus(
                $server->migration?->fresh(),
            );
        }
    }
}