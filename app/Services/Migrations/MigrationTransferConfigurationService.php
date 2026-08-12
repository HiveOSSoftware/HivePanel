<?php

namespace App\Services\Migrations;

use App\Models\PlatformMigration;
use Illuminate\Validation\ValidationException;

class MigrationTransferConfigurationService
{
    public function update(
        PlatformMigration $migration,
        array $nodes,
    ): void {
        $sourceConfig = $migration->source_config ?? [];
        $existing = $sourceConfig['transfer_nodes'] ?? [];

        foreach ($nodes as $sourceNode => $config) {
            $sourceNode = trim((string) $sourceNode);

            if ($sourceNode === '') {
                continue;
            }

            $current = $existing[$sourceNode] ?? [];

            $password = filled($config['password'] ?? null)
                ? $config['password']
                : ($current['password'] ?? null);

            if (blank($password)) {
                throw ValidationException::withMessages([
                    'transfer' => "A password is required for source node {$sourceNode}.",
                ]);
            }

            $existing[$sourceNode] = [
                'protocol' => $config['protocol'] ?? 'sftp',
                'host' => trim((string) ($config['host'] ?? '')),
                'port' => (int) ($config['port'] ?? 22),
                'username' => trim((string) ($config['username'] ?? '')),
                'password' => $password,
                'path_template' => trim(
                    (string) (
                        $config['path_template']
                        ?? '/var/lib/pterodactyl/volumes/{uuid}'
                    )
                ),
            ];
        }

        $sourceConfig['transfer_nodes'] = $existing;

        $migration->forceFill([
            'source_config' => $sourceConfig,
        ])->save();
    }

    public function frontend(
        PlatformMigration $migration,
        array $sourceNodes,
    ): array {
        $configured = data_get(
            $migration->source_config,
            'transfer_nodes',
            [],
        );

        return collect($sourceNodes)
            ->map(function (string $sourceNode) use ($configured) {
                $config = $configured[$sourceNode] ?? [];

                return [
                    'source_node' => $sourceNode,
                    'protocol' => $config['protocol'] ?? 'sftp',
                    'host' => $config['host'] ?? '',
                    'port' => (int) ($config['port'] ?? 22),
                    'username' => $config['username'] ?? '',
                    'path_template' => $config['path_template']
                        ?? '/var/lib/pterodactyl/volumes/{uuid}',
                    'has_password' => filled(
                        $config['password'] ?? null
                    ),
                ];
            })
            ->values()
            ->all();
    }

    public function complete(
        PlatformMigration $migration,
        array $sourceNodes,
    ): bool {
        $configured = data_get(
            $migration->source_config,
            'transfer_nodes',
            [],
        );

        foreach ($sourceNodes as $sourceNode) {
            $config = $configured[$sourceNode] ?? null;

            if (
                ! is_array($config)
                || blank($config['host'] ?? null)
                || blank($config['username'] ?? null)
                || blank($config['password'] ?? null)
                || blank($config['path_template'] ?? null)
            ) {
                return false;
            }
        }

        return true;
    }
}