<?php

namespace App\Services\Migrations;

use App\Models\PlatformMigration;
use App\Models\PlatformMigrationServer;
use App\Services\Cells\CellSyncService;
use App\Services\Node\FileNodeClient;
use Throwable;

class MigrationVerificationService
{
    public function __construct(
        private readonly CellSyncService $sync,
        private readonly FileNodeClient $files,
    ) {
    }

    public function verify(PlatformMigration $migration): array
    {
        $migration->load([
            'servers' => fn ($query) => $query
                ->where('selected', true)
                ->with([
                    'destinationCell.node',
                    'destinationCell.allocation',
                    'destinationCell.allocations',
                ]),
        ]);

        $serverReports = $migration->servers
            ->map(
                fn (PlatformMigrationServer $server) =>
                    $this->verifyServer($server)
            )
            ->values()
            ->all();

        $checks = collect($serverReports)
            ->flatMap(
                fn (array $server) =>
                    $server['checks']
                    ?? []
            );

        $failed = $checks
            ->where('status', 'failed')
            ->count();

        $warnings = $checks
            ->where('status', 'warning')
            ->count();

        $passed = $checks
            ->where('status', 'passed')
            ->count();

        $status = $failed > 0
            ? 'verification_failed'
            : 'verified';

        $report = [
            'status' => $status,
            'verified' => $failed === 0,
            'checked_at' => now()->toISOString(),
            'summary' => [
                'servers' => count($serverReports),
                'passed' => $passed,
                'warnings' => $warnings,
                'failed' => $failed,
            ],
            'servers' => $serverReports,
        ];

        $sourceConfig = $migration->source_config ?? [];
        $sourceConfig['verification'] = $report;

        $history = array_values(
            (array) data_get(
                $sourceConfig,
                'execution_history',
                [],
            )
        );

        $history[] = [
            'type' => 'verification',
            'at' => $report['checked_at'],
            'status' => $report['status'],
            'passed' => $report['summary']['passed'],
            'warnings' => $report['summary']['warnings'],
            'failed' => $report['summary']['failed'],
        ];

        $sourceConfig['execution_history'] =
            array_slice($history, -100);

        $migration->forceFill([
            'source_config' => $sourceConfig,
            'status' => $status,
            'current_stage' => $failed > 0
                ? 'Post-migration verification found issues'
                : 'Migration verified',
            'progress' => 100,
            'error' => $failed > 0
                ? "{$failed} post-migration verification check(s) failed."
                : null,
        ])->save();

        return $report;
    }

    private function verifyServer(
        PlatformMigrationServer $server,
    ): array {
        $checks = [];

        $cell = $server->destinationCell;

        $checks[] = $this->check(
            'cell',
            'Destination Cell',
            $cell !== null
                ? 'passed'
                : 'failed',
            $cell !== null
                ? 'Destination Cell exists in HivePanel.'
                : 'Destination Cell is missing from HivePanel.',
        );

        if (! $cell) {
            return [
                'server_id' => (string) $server->id,
                'name' => $server->name,
                'cell_id' => null,
                'status' => 'failed',
                'checks' => $checks,
            ];
        }

        $checks[] = $this->check(
            'allocation',
            'Allocation',
            $cell->allocation
                ? 'passed'
                : 'failed',
            $cell->allocation
                ? "{$cell->allocation->ip}:{$cell->allocation->port} is assigned."
                : 'No primary allocation is assigned to the Cell.',
        );

        $installStatus = $cell->install_status instanceof \BackedEnum
            ? $cell->install_status->value
            : (string) $cell->install_status;

        $checks[] = $this->check(
            'install_status',
            'Install State',
            $installStatus === 'installed'
                ? 'passed'
                : 'failed',
            $installStatus === 'installed'
                ? 'Cell is marked as installed and accessible.'
                : "Cell install state is {$installStatus}.",
        );

        try {
            $listing = $this->files->files(
                $cell,
                '',
            );

            $fileEntries = $this->fileEntries(
                $listing
            );

            $fileCount = count($fileEntries);

            $checks[] = $this->check(
                'files',
                'Imported Files',
                $fileCount > 0
                    ? 'passed'
                    : 'warning',
                $fileCount > 0
                    ? "{$fileCount} root-level file/folder entr"
                        . ($fileCount === 1 ? 'y' : 'ies')
                        . ' found on the Worker.'
                    : 'The Worker root directory is readable but currently contains no visible entries.',
                [
                    'root_entries' => $fileCount,
                ],
            );
        } catch (Throwable $exception) {
            report($exception);

            $checks[] = $this->check(
                'files',
                'Imported Files',
                'failed',
                'HivePanel could not read the migrated Cell files from the Worker.',
                [
                    'error' => $exception->getMessage(),
                ],
            );
        }

        try {
            $inspection = $this->sync->inspect(
                $cell
            );

            $checks[] = $this->check(
                'worker_sync',
                'Worker Definition',
                ($inspection['synced'] ?? false)
                    ? 'passed'
                    : 'failed',
                ($inspection['synced'] ?? false)
                    ? 'Worker definition matches HivePanel.'
                    : (
                        $inspection['message']
                        ?? 'Worker definition is not in sync.'
                    ),
                [
                    'sync_status' =>
                        $inspection['status']
                        ?? null,
                    'differences' =>
                        $inspection['differences']
                        ?? [],
                ],
            );
        } catch (Throwable $exception) {
            report($exception);

            $checks[] = $this->check(
                'worker_sync',
                'Worker Definition',
                'failed',
                'HivePanel could not verify the Worker definition.',
                [
                    'error' => $exception->getMessage(),
                ],
            );
        }

        $selectedDatabases = collect(
            $server->database_plan
            ?? []
        )->where(
            'selected',
            true
        );

        if ($selectedDatabases->isEmpty()) {
            $checks[] = $this->check(
                'databases',
                'Databases',
                'skipped',
                'No databases were selected for this server.',
            );
        } else {
            $databaseFailures = $selectedDatabases
                ->reject(
                    fn (array $database) =>
                        ($database['status'] ?? null)
                        === 'completed'
                );

            $checks[] = $this->check(
                'databases',
                'Databases',
                $databaseFailures->isEmpty()
                    ? 'passed'
                    : 'failed',
                $databaseFailures->isEmpty()
                    ? "{$selectedDatabases->count()} selected database"
                        . ($selectedDatabases->count() === 1 ? '' : 's')
                        . ' transferred successfully.'
                    : "{$databaseFailures->count()} selected database"
                        . ($databaseFailures->count() === 1 ? ' has' : 's have')
                        . ' not completed successfully.',
            );
        }

        $transferNode = (array) data_get(
            $server->migration?->source_config,
            'transfer_nodes.'
                . $server->source_node_name,
            [],
        );

        $protocol = (string) (
            $transferNode['protocol']
            ?? 'sftp'
        );

        $checks[] = $this->check(
            'source_retained',
            'Source Retained',
            'passed',
            $protocol === 'local'
                ? 'In-place migration used copy mode; the original source volume was not moved or deleted.'
                : 'Remote migration imports a copy and does not delete source-node files.',
        );

        $failed = collect($checks)
            ->contains(
                fn (array $check) =>
                    $check['status']
                    === 'failed'
            );

        $warning = collect($checks)
            ->contains(
                fn (array $check) =>
                    $check['status']
                    === 'warning'
            );

        return [
            'server_id' => (string) $server->id,
            'name' => $server->name,
            'cell_id' => (string) $cell->id,
            'status' => $failed
                ? 'failed'
                : (
                    $warning
                        ? 'warning'
                        : 'passed'
                ),
            'checks' => $checks,
        ];
    }

    private function fileEntries(
        array $listing,
    ): array {
        if (
            isset($listing['files'])
            && is_array($listing['files'])
        ) {
            return array_values(
                array_filter(
                    $listing['files'],
                    fn ($entry) =>
                        is_array($entry)
                        && ! str_starts_with(
                            (string) (
                                $entry['name']
                                ?? ''
                            ),
                            '__backup_mount__',
                        )
                )
            );
        }

        if (array_is_list($listing)) {
            return array_values(
                array_filter(
                    $listing,
                    fn ($entry) =>
                        is_array($entry)
                )
            );
        }

        return [];
    }

    private function check(
        string $key,
        string $label,
        string $status,
        string $message,
        array $details = [],
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'message' => $message,
            'details' => $details,
        ];
    }
}