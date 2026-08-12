<?php

namespace App\Services\Migrations;

use App\Models\PlatformMigration;
use App\Models\PlatformMigrationServer;
use App\Services\Migrations\Contracts\MigrationSourceConnector;
use App\Services\Migrations\Sources\PterodactylDatabaseSource;
use App\Services\Migrations\Sources\PterodactylSourceConnector;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class MigrationDiscoveryService
{
    public function connector(PlatformMigration $migration): MigrationSourceConnector
    {
        $config = $migration->source_config ?? [];

        return match ($migration->source_type) {
            'pterodactyl' => new PterodactylSourceConnector(
                panelUrl: (string) ($config['panel_url'] ?? ''),
                apiKey: (string) ($config['api_key'] ?? ''),
            ),
            default => throw new InvalidArgumentException(
                "Unsupported migration source: {$migration->source_type}"
            ),
        };
    }

    public function discover(PlatformMigration $migration): int
    {
        $migration->forceFill([
            'status' => 'discovering',
            'current_stage' => 'Connecting to source panel',
            'progress' => 10,
            'error' => null,
        ])->save();

        try {
            $connector = $this->connector($migration);
            $connector->testConnection();

            $migration->forceFill([
                'current_stage' => 'Connection verified',
                'progress' => 20,
            ])->save();

            $migration->forceFill([
                'current_stage' => 'Discovering source servers',
                'progress' => 30,
            ])->save();

            $servers = $connector->discoverServers();

            $databaseUsers = [];
            $serverDatabases = [];

            if ((bool) data_get(
                $migration->source_config,
                'database.enabled',
                false,
            )) {
                $migration->forceFill([
                    'current_stage' => 'Reading Pterodactyl database metadata',
                    'progress' => 65,
                ])->save();

                $databaseSource = $this->databaseSource($migration);
                $databaseSource->testConnection();

                $databaseUsers = $databaseSource->users();
                $serverDatabases = $databaseSource->serverDatabases();

                $sourceConfig = $migration->source_config ?? [];

                $sourceConfig['database_discovery'] = [
                    'users' => $databaseUsers,
                    'server_database_count' => collect(
                        $serverDatabases,
                    )->flatten(1)->count(),
                    'discovered_at' => now()->toISOString(),
                ];

                $migration->forceFill([
                    'source_config' => $sourceConfig,
                ])->save();
            }

            $migration->forceFill([
                'current_stage' => 'Saving discovered inventory',
                'progress' => 80,
            ])->save();

            DB::transaction(function () use (
                $migration,
                $servers,
                $serverDatabases,
            ): void {
                $sourceIds = [];

                foreach ($servers as $server) {
                    $data = $server->toArray();
                    $sourceIds[] = $data['source_server_id'];

                    $sourceMetadata = $data['source_metadata'] ?? [];

                    $sourceMetadata['databases'] = $serverDatabases[
                        (string) $data['source_server_id']
                    ] ?? [];

                    $data['source_metadata'] = $sourceMetadata;

                    PlatformMigrationServer::updateOrCreate(
                        [
                            'platform_migration_id' => $migration->id,
                            'source_server_id' => $data['source_server_id'],
                        ],
                        [
                            ...$data,
                            'status' => 'discovered',
                            'current_stage' => null,
                            'progress' => 0,
                            'error' => null,
                        ]
                    );
                }

                $query = $migration->servers();

                if (count($sourceIds) > 0) {
                    $query->whereNotIn('source_server_id', $sourceIds);
                }

                $query->delete();

                $migration->forceFill([
                    'status' => 'ready',
                    'current_stage' => 'Discovery complete',
                    'progress' => 100,
                    'error' => null,
                    'discovered_at' => now(),
                ])->save();
            });

            return count($servers);
        } catch (Throwable $exception) {
            report($exception);

            $migration->forceFill([
                'status' => 'failed',
                'current_stage' => 'Discovery failed',
                'progress' => 0,
                'error' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    public function databaseSource(
        PlatformMigration $migration,
    ): PterodactylDatabaseSource {
        $config = (array) data_get(
            $migration->source_config,
            'database',
            [],
        );

        return new PterodactylDatabaseSource(
            host: (string) ($config['host'] ?? ''),
            port: (int) ($config['port'] ?? 3306),
            database: (string) ($config['database'] ?? ''),
            username: (string) ($config['username'] ?? ''),
            password: (string) ($config['password'] ?? ''),
        );
    }

}