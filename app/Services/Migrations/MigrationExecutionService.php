<?php

namespace App\Services\Migrations;

use App\Models\Comb;
use App\Models\NodeAllocation;
use App\Models\PlatformMigration;
use App\Models\PlatformMigrationServer;
use App\Models\User;
use App\Services\Cells\CellProvisioningService;
use App\Services\Node\ImporterNodeClient;
use RuntimeException;

class MigrationExecutionService
{
    public function __construct(
        private readonly CellProvisioningService $provisioning,
        private readonly ImporterNodeClient $importer,
    ) {
    }

    public function startServer(
        PlatformMigrationServer $server,
    ): void {
        $server->loadMissing([
            'migration',
            'destinationNode',
            'destinationOwner',
            'destinationCell',
        ]);

        if ($server->destinationCell) {
            $this->startFileImport(
                $server,
            );

            return;
        }

        $migration = $server->migration;

        if (! $migration) {
            throw new RuntimeException(
                'Migration record is missing.'
            );
        }

        if ($server->status !== 'queued') {
            throw new RuntimeException(
                "Server '{$server->name}' is not queued for execution."
            );
        }

        $server->forceFill([
            'status' => 'creating_cell',
            'current_stage' => 'Creating destination Cell',
            'progress' => 5,
            'error' => null,
            'started_at' => $server->started_at
                ?? now(),
        ])->save();

        $owner = User::query()->find(
            $server->destination_owner_id
        );

        if (! $owner) {
            throw new RuntimeException(
                "Destination owner is missing for '{$server->name}'."
            );
        }

        $comb = Comb::query()
            ->where(
                'external_id',
                $server->destination_comb,
            )
            ->first();

        if (! $comb) {
            throw new RuntimeException(
                "Destination Comb is missing for '{$server->name}'."
            );
        }

        $allocationData = $this->resolveAllocations(
            $server,
        );

        $resources = (array) data_get(
            $server->execution_plan,
            'resources',
            [],
        );

        $sourceEnvironment = (array) data_get(
            $server->source_metadata,
            'environment',
            [],
        );

        $version = $this->resolveVersion(
            $sourceEnvironment,
        );

        $variables = [
            ...$sourceEnvironment,
        ];

        $cell = $this->provisioning->provision(
            [
                'node_id' => $server->destination_node_id,
                'allocation_id' => $allocationData[
                    'primary'
                ]->id,
                'additional_allocation_ids' =>
                    $allocationData['additional']
                        ->pluck('id')
                        ->values()
                        ->all(),

                'name' => $server->name,
                'description' => data_get(
                    $server->source_metadata,
                    'description',
                ),
                'start_on_completion' => false,

                'comb_id' => $comb->id,
                'version' => $version,
                'skip_install_script' => true,

                'memory_mb' => (int) (
                    $resources['memory_mb']
                    ?? 0
                ),
                'overhead_memory_mb' => 0,
                'swap_mb' => (int) (
                    $resources['swap_mb']
                    ?? 0
                ),
                'disk_mb' => (int) (
                    $resources['disk_mb']
                    ?? 0
                ),
                'cpu_percent' => (int) (
                    $resources['cpu_percent']
                    ?? 0
                ),
                'cpu_pinning' => $resources[
                    'cpu_pinning'
                ] ?? null,
                'io_weight' => (int) (
                    $resources['io_weight']
                    ?? 500
                ),
                'oom_killer' => true,
                'exclude_from_resource_calculation' => false,

                'database_limit' => $resources[
                    'database_limit'
                ] ?? null,
                'allocation_limit' => $resources[
                    'allocation_limit'
                ] ?? null,
                'backup_limit' => $resources[
                    'backup_limit'
                ] ?? null,
                'backup_storage_mb' => null,

                'docker_image' => $resources[
                    'docker_image'
                ] ?? data_get(
                    $server->source_metadata,
                    'docker_image',
                ),
                'startup_command' => $resources[
                    'startup_command'
                ] ?? data_get(
                    $server->source_metadata,
                    'startup',
                ),

                'variables' => $variables,
            ],
            $owner,
            $allocationData['all']
                ->pluck('id')
                ->values()
                ->all(),
        );

        $server->forceFill([
            'destination_cell_id' => $cell->id,
            'status' => 'transferring',
            'current_stage' => 'Starting file transfer',
            'progress' => 10,
            'error' => null,
        ])->save();

        $this->startFileImport(
            $server->fresh([
                'migration',
                'destinationCell',
            ]),
        );
    }

    public function importerStatus(
        PlatformMigrationServer $server,
    ): array {
        $server->loadMissing(
            'destinationCell'
        );

        if (! $server->destinationCell) {
            throw new RuntimeException(
                'Destination Cell has not been created.'
            );
        }

        return $this->importer->importerStatus(
            $server->destinationCell,
        );
    }

    public function applyImporterProgress(
        PlatformMigrationServer $server,
        array $status,
    ): string {
        $stage = trim(
            (string) (
                $status['stage']
                ?? 'Transferring'
            )
        );

        $percent = max(
            0,
            min(
                100,
                (int) (
                    $status['percent']
                    ?? 0
                ),
            ),
        );

        $running = (bool) (
            $status['running']
            ?? false
        );

        $error = trim(
            (string) (
                $status['error']
                ?? ''
            )
        );

        if (
            strcasecmp($stage, 'Failed') === 0
            || (
                ! $running
                && $error !== ''
                && strcasecmp(
                    $stage,
                    'Complete',
                ) !== 0
            )
        ) {
            $server->forceFill([
                'status' => 'failed',
                'current_stage' => 'File transfer failed',
                'progress' => $percent,
                'error' => $error !== ''
                    ? $error
                    : (
                        $status['message']
                        ?? 'File transfer failed.'
                    ),
            ])->save();

            return 'failed';
        }

        if (
            ! $running
            && strcasecmp(
                $stage,
                'Complete',
            ) === 0
        ) {
            $server->loadMissing(
                'destinationCell'
            );

            if (! $server->destinationCell) {
                throw new RuntimeException(
                    'File transfer completed, but the destination Cell could not be resolved.'
                );
            }

            $server->destinationCell->forceFill([
                'install_status' => 'installed',
                'install_failure_reason' => null,
                'installed_at' => now(),
            ])->save();

            $selectedDatabases = collect(
                $server->database_plan
                ?? []
            )
                ->where('selected', true)
                ->count();

            if ($selectedDatabases > 0) {
                $server->forceFill([
                    'status' => 'database_pending',
                    'current_stage' => 'Files complete; databases pending',
                    'progress' => 85,
                    'error' => $error !== ''
                        ? $error
                        : null,
                ])->save();

                return 'database_pending';
            }

            $server->forceFill([
                'status' => 'completed',
                'current_stage' => 'Migration complete',
                'progress' => 100,
                'error' => $error !== ''
                    ? $error
                    : null,
                'completed_at' => now(),
            ])->save();

            return 'completed';
        }

        $server->forceFill([
            'status' => 'transferring',
            'current_stage' => $stage,
            'progress' => max(
                10,
                min(
                    80,
                    10 + (int) floor(
                        $percent * 0.7
                    ),
                ),
            ),
            'error' => null,
        ])->save();

        return 'running';
    }

    public function failServer(
        PlatformMigrationServer $server,
        string $message,
    ): void {
        $server->forceFill([
            'status' => 'failed',
            'current_stage' => 'Migration failed',
            'error' => $message,
        ])->save();

        $this->refreshMigrationStatus(
            $server->migration,
        );
    }

    public function refreshMigrationStatus(
        ?PlatformMigration $migration,
    ): void {
        if (! $migration) {
            return;
        }

        $servers = $migration->servers()
            ->where('selected', true)
            ->get([
                'status',
                'progress',
            ]);

        if ($servers->isEmpty()) {
            return;
        }

        $total = $servers->count();

        $completed = $servers
            ->where('status', 'completed')
            ->count();

        $failed = $servers
            ->whereIn(
                'status',
                [
                    'failed',
                    'database_failed',
                ],
            )
            ->count();

        $databasePending = $servers
            ->where('status', 'database_pending')
            ->count();

        $active = $servers->filter(
            fn ($server) => in_array(
                $server->status,
                [
                    'queued',
                    'creating_cell',
                    'transferring',
                    'database_transferring',
                ],
                true
            )
        )->count();

        $progress = (int) floor(
            $servers->avg(
                fn ($server) =>
                    (int) $server->progress
            )
        );

        if ($active > 0) {
            $migration->forceFill([
                'status' => 'running',
                'current_stage' =>
                    "{$completed}/{$total} servers completed",
                'progress' => $progress,
                'error' => null,
            ])->save();

            return;
        }

        if ($databasePending > 0) {
            $migration->forceFill([
                'status' => 'database_pending',
                'current_stage' =>
                    "{$databasePending} server(s) awaiting database transfer",
                'progress' => $progress,
                'error' => $failed > 0
                    ? "{$failed} server(s) failed."
                    : null,
            ])->save();

            return;
        }

        if ($completed + $failed === $total) {
            $migration->forceFill([
                'status' => $failed > 0
                    ? 'completed_with_errors'
                    : 'completed',
                'current_stage' => $failed > 0
                    ? "{$completed} completed, {$failed} failed"
                    : 'Migration complete',
                'progress' => 100,
                'error' => $failed > 0
                    ? "{$failed} server(s) failed."
                    : null,
            ])->save();
        }
    }

    private function startFileImport(
        PlatformMigrationServer $server,
    ): void {
        $server->loadMissing([
            'migration',
            'destinationCell',
        ]);

        if (! $server->destinationCell) {
            throw new RuntimeException(
                'Destination Cell is missing.'
            );
        }

        $migration = $server->migration;

        $sourceNode = (string) (
            $server->source_node_name
            ?? ''
        );

        $config = (array) data_get(
            $migration?->source_config,
            'transfer_nodes.' . $sourceNode,
            [],
        );

        if ($config === []) {
            throw new RuntimeException(
                "Transfer access is not configured for source node {$sourceNode}."
            );
        }

        $protocol = strtolower(trim((string) (
            $config['protocol']
            ?? 'sftp'
        )));

        if (! in_array($protocol, ['sftp', 'local'], true)) {
            throw new RuntimeException(
                'The Worker currently supports remote SFTP and in-place local imports only.'
            );
        }

        $pathTemplate = (string) (
            $config['path_template']
            ?? '/var/lib/pterodactyl/volumes/{uuid}'
        );

        $remotePath = str_replace(
            '{uuid}',
            (string) $server->source_uuid,
            $pathTemplate,
        );

        if (
            $remotePath === ''
            || str_contains($remotePath, '{uuid}')
        ) {
            throw new RuntimeException(
                "The source path template for {$sourceNode} could not be resolved."
            );
        }

        $payload = [
            'protocol' => $protocol,
            'host' => '',
            'port' => 0,
            'username' => '',
            'auth_type' => '',
            'password' => '',
            'private_key' => '',
            'private_key_passphrase' => '',
            'remote_path' => $remotePath,
            'options' => [
                'importWorlds' => true,
                'importPlugins' => true,
                'importConfigs' => true,
                'importMods' => true,
                'importServerJar' => true,
                'wipeBeforeImport' => false,
            ],
        ];

        if ($protocol === 'sftp') {
            $payload = [
                ...$payload,
                'host' => (string) ($config['host'] ?? ''),
                'port' => (int) ($config['port'] ?? 22),
                'username' => (string) ($config['username'] ?? ''),
                'auth_type' => (string) (
                    $config['auth_type']
                    ?? (
                        filled($config['private_key'] ?? null)
                            ? 'private_key'
                            : 'password'
                    )
                ),
                'password' => (string) ($config['password'] ?? ''),
                'private_key' => (string) ($config['private_key'] ?? ''),
                'private_key_passphrase' => (string) (
                    $config['private_key_passphrase']
                    ?? ''
                ),
            ];
        }

        $this->importer->startImporter(
            $server->destinationCell,
            $payload,
        );

        $server->forceFill([
            'status' => 'transferring',
            'current_stage' => $protocol === 'local'
                ? 'Local file copy started'
                : 'File transfer started',
            'progress' => 10,
            'error' => null,
        ])->save();
    }

    private function resolveAllocations(
        PlatformMigrationServer $server,
    ): array {
        $plans = collect(
            (array) data_get(
                $server->execution_plan,
                'allocations',
                [],
            )
        );

        if ($plans->isEmpty()) {
            throw new RuntimeException(
                "No destination allocations are planned for '{$server->name}'."
            );
        }

        $resolved = $plans->map(function (
            array $plan
        ) use ($server) {
            $destination = (array) (
                $plan['destination']
                ?? []
            );

            $allocation = null;

            if (filled($destination['id'] ?? null)) {
                $allocation = NodeAllocation::query()
                    ->where(
                        'id',
                        $destination['id'],
                    )
                    ->where(
                        'node_id',
                        $server->destination_node_id,
                    )
                    ->first();
            }

            if (
                ! $allocation
                && filled($destination['ip'] ?? null)
                && filled($destination['port'] ?? null)
            ) {
                $allocation = NodeAllocation::query()
                    ->where(
                        'node_id',
                        $server->destination_node_id,
                    )
                    ->where(
                        'ip',
                        $destination['ip'],
                    )
                    ->where(
                        'port',
                        (int) $destination['port'],
                    )
                    ->first();
            }

            if (! $allocation) {
                throw new RuntimeException(
                    "A prepared allocation could not be resolved for '{$server->name}'."
                );
            }

            if (
                filled($allocation->cell_id)
                || ! (bool) $allocation->is_reserved
            ) {
                throw new RuntimeException(
                    "Prepared allocation {$allocation->ip}:{$allocation->port} is no longer available."
                );
            }

            return [
                'allocation' => $allocation,
                'primary' => (bool) data_get(
                    $plan,
                    'source.is_primary',
                    false,
                ),
            ];
        });

        $primaryEntry = $resolved->first(
            fn (array $entry) =>
                $entry['primary']
        ) ?? $resolved->first();

        $primary = $primaryEntry[
            'allocation'
        ];

        $additional = $resolved
            ->reject(
                fn (array $entry) =>
                    (string) $entry[
                        'allocation'
                    ]->id
                    === (string) $primary->id
            )
            ->map(
                fn (array $entry) =>
                    $entry['allocation']
            )
            ->values();

        return [
            'primary' => $primary,
            'additional' => $additional,
            'all' => collect([
                $primary,
                ...$additional->all(),
            ]),
        ];
    }

    private function resolveVersion(
        array $environment,
    ): string {
        foreach (
            [
                'MINECRAFT_VERSION',
                'SERVER_VERSION',
                'VERSION',
                'MC_VERSION',
            ] as $key
        ) {
            $value = trim(
                (string) (
                    $environment[$key]
                    ?? ''
                )
            );

            if ($value !== '') {
                return $value;
            }
        }

        return 'latest';
    }
}