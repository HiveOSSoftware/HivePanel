<?php

namespace App\Jobs\Migrations;

use App\Models\PlatformMigration;
use App\Services\Migrations\MigrationDiscoveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class DiscoverPlatformMigration implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(
        public readonly string $migrationId,
    ) {
    }

    public function handle(MigrationDiscoveryService $discovery): void
    {
        $migration = PlatformMigration::query()->find($this->migrationId);

        if (! $migration) {
            return;
        }

        $discovery->discover($migration);
    }

    public function failed(?Throwable $exception): void
    {
        $migration = PlatformMigration::query()->find($this->migrationId);

        if (! $migration) {
            return;
        }

        $migration->forceFill([
            'status' => 'failed',
            'current_stage' => 'Discovery failed',
            'progress' => 0,
            'error' => $exception?->getMessage() ?: 'The discovery job failed unexpectedly.',
        ])->save();
    }
}