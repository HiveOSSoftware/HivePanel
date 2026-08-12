<?php

namespace App\Jobs\Migrations;

use App\Models\PlatformMigrationServer;
use App\Services\Migrations\MigrationExecutionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class MigratePlatformServer implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(
        public readonly string $migrationServerId,
    ) {
    }

    public function handle(
        MigrationExecutionService $execution,
    ): void {
        $server = PlatformMigrationServer::query()
            ->with('migration')
            ->find($this->migrationServerId);

        if (! $server) {
            return;
        }

        try {
            $execution->startServer(
                $server,
            );

            MonitorPlatformServerImport::dispatch(
                $server->id,
            )->delay(
                now()->addSeconds(5)
            );

            $execution->refreshMigrationStatus(
                $server->migration,
            );
        } catch (Throwable $exception) {
            report($exception);

            $execution->failServer(
                $server,
                $exception->getMessage()
                    ?: 'The server migration failed.',
            );
        }
    }
}