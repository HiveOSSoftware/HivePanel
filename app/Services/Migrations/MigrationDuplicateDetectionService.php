<?php

namespace App\Services\Migrations;

use App\Models\Cell;
use App\Models\PlatformMigration;
use App\Models\PlatformMigrationServer;
use Illuminate\Support\Str;

class MigrationDuplicateDetectionService
{
    public function find(
        PlatformMigration $migration,
        string $sourceUuid,
    ): ?array {
        $sourceUuid = strtolower(
            trim($sourceUuid)
        );

        if ($sourceUuid === '') {
            return null;
        }

        $panelUrl = $this->normalisePanelUrl(
            (string) data_get(
                $migration->source_config,
                'panel_url',
                ''
            )
        );

        $cell = Cell::query()
            ->where(
                'metadata->migration_source->source_uuid',
                $sourceUuid
            )
            ->get()
            ->first(function (Cell $cell) use ($panelUrl) {
                $cellPanelUrl = $this->normalisePanelUrl(
                    (string) data_get(
                        $cell->metadata,
                        'migration_source.panel_url',
                        ''
                    )
                );

                return $panelUrl !== ''
                    && $cellPanelUrl === $panelUrl;
            });

        if ($cell) {
            return [
                'already_migrated' => true,
                'cell_id' => (string) $cell->id,
                'cell_name' => $cell->name,
                'migration_id' => data_get(
                    $cell->metadata,
                    'migration_source.migration_id'
                ),
                'migration_name' => data_get(
                    $cell->metadata,
                    'migration_source.migration_name'
                ),
                'detected_by' => 'cell_provenance',
            ];
        }

        $historical = PlatformMigrationServer::query()
            ->whereRaw(
                'LOWER(source_uuid) = ?',
                [
                    $sourceUuid,
                ]
            )
            ->whereNotNull(
                'destination_cell_id'
            )
            ->with([
                'migration',
                'destinationCell:id,name',
            ])
            ->get()
            ->first(function (
                PlatformMigrationServer $server
            ) use (
                $migration,
                $panelUrl
            ) {
                if (! $server->destinationCell) {
                    return false;
                }

                $historicalPanelUrl = $this->normalisePanelUrl(
                    (string) data_get(
                        $server->migration?->source_config,
                        'panel_url',
                        ''
                    )
                );

                if (
                    $panelUrl === ''
                    || $historicalPanelUrl !== $panelUrl
                ) {
                    return false;
                }

                return true;
            });

        if (! $historical) {
            return null;
        }

        return [
            'already_migrated' => true,
            'cell_id' => (string) $historical->destinationCell->id,
            'cell_name' => $historical->destinationCell->name,
            'migration_id' => (string) $historical->platform_migration_id,
            'migration_name' => $historical->migration?->name,
            'detected_by' => 'migration_history',
        ];
    }

    public function assertNotMigrated(
        PlatformMigration $migration,
        PlatformMigrationServer $server,
    ): void {
        $duplicate = $this->find(
            $migration,
            (string) $server->source_uuid,
        );

        if (! $duplicate) {
            return;
        }

        abort(
            422,
            "Server '{$server->name}' has already been migrated to HivePanel Cell '{$duplicate['cell_name']}'."
        );
    }

    private function normalisePanelUrl(
        string $url,
    ): string {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return strtolower(
                rtrim($url, '/')
            );
        }

        $scheme = strtolower(
            (string) (
                $parts['scheme']
                ?? 'https'
            )
        );

        $host = strtolower(
            (string) (
                $parts['host']
                ?? ''
            )
        );

        $port = isset($parts['port'])
            ? ':' . $parts['port']
            : '';

        $path = rtrim(
            (string) (
                $parts['path']
                ?? ''
            ),
            '/'
        );

        return "{$scheme}://{$host}{$port}{$path}";
    }
}