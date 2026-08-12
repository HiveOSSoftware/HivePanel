<?php

namespace App\Jobs\Migrations;

use App\Models\PlatformMigration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExecutePlatformMigration implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(
        public readonly string $migrationId,
    ) {
    }

    public function handle(): void
    {
        $migration = PlatformMigration::query()
            ->find($this->migrationId);

        if (! $migration) {
            return;
        }

        if ($migration->status !== 'execution_ready') {
            return;
        }

        $servers = $migration->servers()
            ->where('selected', true)
            ->where('status', 'prepared')
            ->get();

        if ($servers->isEmpty()) {
            $migration->forceFill([
                'status' => 'failed',
                'current_stage' => 'No prepared servers found',
                'error' => 'The migration does not contain any prepared servers.',
            ])->save();

            return;
        }

        $migration->forceFill([
            'status' => 'running',
            'current_stage' => 'Queueing server migrations',
            'progress' => 0,
            'error' => null,
        ])->save();

        foreach ($servers as $server) {
            $server->forceFill([
                'status' => 'queued',
                'current_stage' => 'Waiting for migration worker',
                'progress' => 0,
                'error' => null,
                'started_at' => null,
                'completed_at' => null,
            ])->save();

            MigratePlatformServer::dispatch(
                $server->id,
            );
        }
    }
}