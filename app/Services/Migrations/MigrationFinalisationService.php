<?php

namespace App\Services\Migrations;

use App\Models\PlatformMigration;
use RuntimeException;

class MigrationFinalisationService
{
    public function finalise(PlatformMigration $migration): array
    {
        $verification = (array) data_get(
            $migration->source_config,
            'verification',
            [],
        );

        if (
            $migration->status !== 'verified'
            || ! (bool) (
                $verification['verified']
                ?? false
            )
        ) {
            throw new RuntimeException(
                'The migration must pass post-migration verification before it can be finalised.'
            );
        }

        $sourceConfig = $migration->source_config ?? [];

        $summary = [
            'panel_url' => data_get(
                $sourceConfig,
                'panel_url',
            ),
            'source_type' => $migration->source_type,
            'database_enhancement_used' => (bool) data_get(
                $sourceConfig,
                'database.enabled',
                false,
            ),
            'discovered_users' => count(
                (array) data_get(
                    $sourceConfig,
                    'database_discovery.users',
                    [],
                )
            ),
            'server_database_count' => (int) data_get(
                $sourceConfig,
                'database_discovery.server_database_count',
                0,
            ),
            'source_nodes' => $migration->servers()
                ->where('selected', true)
                ->pluck('source_node_name')
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];

        $sourceConfig = $this->stripCredentials(
            $sourceConfig
        );

        $sourceConfig['source_summary'] =
            $summary;

        $history = array_values(
            (array) data_get(
                $sourceConfig,
                'execution_history',
                [],
            )
        );

        $finalisedAt = now()->toISOString();

        $history[] = [
            'type' => 'finalisation',
            'at' => $finalisedAt,
            'status' => 'finalised',
            'message' =>
                'Source credentials and cached source authentication data were removed.',
        ];

        $sourceConfig['execution_history'] =
            array_slice($history, -100);

        $sourceConfig['finalisation'] = [
            'finalised' => true,
            'finalised_at' => $finalisedAt,
            'credentials_removed' => true,
            'source_data_deleted' => false,
            'source_databases_deleted' => false,
        ];

        $migration->forceFill([
            'source_config' => $sourceConfig,
            'status' => 'finalised',
            'current_stage' => 'Migration finalised',
            'progress' => 100,
            'error' => null,
        ])->save();

        return [
            'status' => 'finalised',
            'finalised_at' => $finalisedAt,
            'credentials_removed' => true,
            'source_data_deleted' => false,
            'source_databases_deleted' => false,
        ];
    }

    private function stripCredentials(
        array $sourceConfig,
    ): array {
        unset(
            $sourceConfig['api_key'],
            $sourceConfig['database_discovery'],
        );

        if (
            isset($sourceConfig['database'])
            && is_array($sourceConfig['database'])
        ) {
            $sourceConfig['database'] = [
                'enabled' => false,
                'host' => data_get(
                    $sourceConfig,
                    'database.host',
                ),
                'port' => data_get(
                    $sourceConfig,
                    'database.port',
                ),
                'database' => data_get(
                    $sourceConfig,
                    'database.database',
                ),
                'credentials_removed' => true,
            ];
        }

        foreach (
            [
                'transfer_nodes',
                'transfer',
            ]
            as $transferKey
        ) {
            if (
                ! isset($sourceConfig[$transferKey])
                || ! is_array(
                    $sourceConfig[$transferKey]
                )
            ) {
                continue;
            }

            foreach (
                $sourceConfig[$transferKey]
                as $node => $config
            ) {
                if (! is_array($config)) {
                    continue;
                }

                $sourceConfig[$transferKey][$node] =
                    $this->stripSensitiveKeys(
                        $config,
                        true,
                    );
            }
        }

        foreach (
            [
                'database_transfer_hosts',
                'database_transfer',
                'database_hosts',
            ]
            as $databaseTransferKey
        ) {
            if (
                ! isset(
                    $sourceConfig[
                        $databaseTransferKey
                    ]
                )
                || ! is_array(
                    $sourceConfig[
                        $databaseTransferKey
                    ]
                )
            ) {
                continue;
            }

            $sourceConfig[
                $databaseTransferKey
            ] = $this->stripSensitiveKeys(
                $sourceConfig[
                    $databaseTransferKey
                ],
                true,
            );
        }

        return $this->stripSensitiveKeys(
            $sourceConfig,
            false,
        );
    }

    private function stripSensitiveKeys(
        array $value,
        bool $removeUsernames,
    ): array {
        foreach (
            array_keys($value)
            as $key
        ) {
            $normalised = strtolower(
                (string) $key
            );

            if (
                $this->isSensitiveKey(
                    $normalised,
                    $removeUsernames,
                )
            ) {
                unset($value[$key]);

                continue;
            }

            if (is_array($value[$key])) {
                $value[$key] =
                    $this->stripSensitiveKeys(
                        $value[$key],
                        $removeUsernames,
                    );
            }
        }

        return $value;
    }

    private function isSensitiveKey(
        string $key,
        bool $removeUsernames,
    ): bool {
        if (
            in_array(
                $key,
                [
                    'api_key',
                    'password',
                    'source_password',
                    'destination_password',
                    'private_key',
                    'private_key_passphrase',
                    'passphrase',
                    'secret',
                    'token',
                    'access_token',
                    'refresh_token',
                    'credential',
                    'credentials',
                    'password_hash',
                ],
                true
            )
        ) {
            return true;
        }

        if (
            str_contains(
                $key,
                'private_key',
            )
            || str_ends_with(
                $key,
                '_password',
            )
            || str_ends_with(
                $key,
                '_secret',
            )
            || str_ends_with(
                $key,
                '_token',
            )
        ) {
            return true;
        }

        if (
            $removeUsernames
            && in_array(
                $key,
                [
                    'username',
                    'source_username',
                    'destination_username',
                ],
                true
            )
        ) {
            return true;
        }

        return false;
    }
}