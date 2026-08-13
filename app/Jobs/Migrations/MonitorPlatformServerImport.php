<?php

namespace App\Jobs\Migrations;

use App\Models\PlatformMigrationServer;
use App\Services\Migrations\MigrationExecutionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class MonitorPlatformServerImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly string $migrationServerId,
    ) {
    }

    public function handle(
        MigrationExecutionService $execution,
    ): void {
        $server = PlatformMigrationServer::query()
            ->with([
                'migration',
                'destinationCell',
            ])
            ->find($this->migrationServerId);

        if (! $server) {
            return;
        }

        if (
            ! in_array(
                $server->status,
                [
                    'transferring',
                    'creating_cell',
                ],
                true
            )
        ) {
            $execution->refreshMigrationStatus(
                $server->migration,
            );

            return;
        }

        try {
            $status = $execution->importerStatus(
                $server,
            );

            $result = $execution->applyImporterProgress(
                $server,
                $status,
            );

            $execution->refreshMigrationStatus(
                $server->migration,
            );

            if ($result === 'running') {
                self::dispatch(
                    $server->id,
                )->delay(
                    now()->addSeconds(5)
                );

                return;
            }

            if ($result === 'database_pending') {
                MigratePlatformServerDatabases::dispatch(
                    $server->id,
                );
            }
        } catch (Throwable $exception) {
            report($exception);

            $execution->failServer(
                $server,
                $exception->getMessage()
                    ?: 'HivePanel could not read file transfer progress.',
            );
        }
    }
}