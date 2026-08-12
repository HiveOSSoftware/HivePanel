<?php

namespace App\Services\Migrations;

use App\Models\PlatformMigration;
use PDO;
use PDOException;
use RuntimeException;
use Illuminate\Validation\ValidationException;

class MigrationDatabaseTransferConfigurationService
{
    public function hosts(PlatformMigration $migration): array
    {
        $migration->loadMissing('servers');

        $hosts = [];

        foreach ($migration->servers as $server) {
            if (! $server->selected) {
                continue;
            }

            foreach ((array) ($server->database_plan ?? []) as $database) {
                if (! (bool) ($database['selected'] ?? false)) {
                    continue;
                }

                $source = (array) data_get(
                    $database,
                    'source.host',
                    [],
                );

                $host = trim((string) ($source['host'] ?? ''));
                $port = (int) ($source['port'] ?? 3306);

                if ($host === '') {
                    continue;
                }

                $key = $this->hostKey(
                    $host,
                    $port,
                );

                $hosts[$key] = [
                    'key' => $key,
                    'name' => $source['name'] ?? $host,
                    'host' => $host,
                    'port' => $port,
                ];
            }
        }

        return array_values($hosts);
    }

    public function frontend(
        PlatformMigration $migration,
    ): array {
        $configured = (array) data_get(
            $migration->execution_config,
            'database_hosts',
            [],
        );

        return collect($this->hosts($migration))
            ->map(function (array $host) use ($configured) {
                $config = (array) (
                    $configured[$host['key']]
                    ?? []
                );

                return [
                    ...$host,

                    'source_username' => data_get(
                        $config,
                        'source.username',
                        '',
                    ),
                    'source_has_password' => filled(data_get(
                        $config,
                        'source.password',
                    )),

                    'destination_host' => data_get(
                        $config,
                        'destination.host',
                        '',
                    ),
                    'destination_port' => (int) data_get(
                        $config,
                        'destination.port',
                        3306,
                    ),
                    'destination_username' => data_get(
                        $config,
                        'destination.username',
                        '',
                    ),
                    'destination_has_password' => filled(data_get(
                        $config,
                        'destination.password',
                    )),
                    'destination_prefix' => data_get(
                        $config,
                        'destination.prefix',
                        'hive_',
                    ),

                    'verified' => (bool) (
                        $config['verified']
                        ?? false
                    ),
                    'verified_at' => $config[
                        'verified_at'
                    ] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    public function update(
        PlatformMigration $migration,
        array $hosts,
    ): void {
        $executionConfig = $migration->execution_config ?? [];
        $existing = (array) (
            $executionConfig['database_hosts']
            ?? []
        );

        foreach ($this->hosts($migration) as $requiredHost) {
            $key = $requiredHost['key'];

            $input = (array) (
                $hosts[$key]
                ?? []
            );

            if ($input === []) {
                throw ValidationException::withMessages([
                    'database_transfer' => "Database transfer credentials are missing for {$requiredHost['host']}:{$requiredHost['port']}.",
                ]);
            }

            $current = (array) (
                $existing[$key]
                ?? []
            );

            $sourcePassword = filled(
                $input['source_password']
                ?? null
            )
                ? (string) $input['source_password']
                : (string) data_get(
                    $current,
                    'source.password',
                    '',
                );

            $destinationPassword = filled(
                $input['destination_password']
                ?? null
            )
                ? (string) $input['destination_password']
                : (string) data_get(
                    $current,
                    'destination.password',
                    '',
                );

            if ($sourcePassword === '') {
                throw ValidationException::withMessages([
                    'database_transfer' => "A source database password is required for {$requiredHost['host']}:{$requiredHost['port']}.",
                ]);
            }

            if ($destinationPassword === '') {
                throw ValidationException::withMessages([
                    'database_transfer' => "A destination database password is required for {$requiredHost['host']}:{$requiredHost['port']}.",
                ]);
            }

            $sourceUsername = trim(
                (string) (
                    $input['source_username']
                    ?? ''
                )
            );

            $destinationHost = trim(
                (string) (
                    $input['destination_host']
                    ?? ''
                )
            );

            $destinationPort = (int) (
                $input['destination_port']
                ?? 3306
            );

            $destinationUsername = trim(
                (string) (
                    $input['destination_username']
                    ?? ''
                )
            );

            $destinationPrefix = trim(
                (string) (
                    $input['destination_prefix']
                    ?? 'hive_'
                )
            );

            if (
                $sourceUsername === ''
                || $destinationHost === ''
                || $destinationUsername === ''
            ) {
                throw ValidationException::withMessages([
                    'database_transfer' => "Database transfer configuration is incomplete for {$requiredHost['host']}:{$requiredHost['port']}.",
                ]);
            }

            $this->testConnection(
                host: $requiredHost['host'],
                port: $requiredHost['port'],
                username: $sourceUsername,
                password: $sourcePassword,
                label: 'source',
            );

            $this->testConnection(
                host: $destinationHost,
                port: $destinationPort,
                username: $destinationUsername,
                password: $destinationPassword,
                label: 'destination',
            );

            $existing[$key] = [
                'source' => [
                    'name' => $requiredHost['name'],
                    'host' => $requiredHost['host'],
                    'port' => $requiredHost['port'],
                    'username' => $sourceUsername,
                    'password' => $sourcePassword,
                ],

                'destination' => [
                    'host' => $destinationHost,
                    'port' => $destinationPort,
                    'username' => $destinationUsername,
                    'password' => $destinationPassword,
                    'prefix' => $destinationPrefix,
                ],

                'verified' => true,
                'verified_at' => now()->toISOString(),
            ];
        }

        $executionConfig['database_hosts'] = $existing;

        $migration->forceFill([
            'execution_config' => $executionConfig,
        ])->save();
    }

    public function complete(
        PlatformMigration $migration,
    ): bool {
        $requiredHosts = $this->hosts($migration);

        if ($requiredHosts === []) {
            return true;
        }

        $configured = (array) data_get(
            $migration->execution_config,
            'database_hosts',
            [],
        );

        foreach ($requiredHosts as $host) {
            $config = (array) (
                $configured[$host['key']]
                ?? []
            );

            if (
                ! (bool) ($config['verified'] ?? false)
                || blank(data_get(
                    $config,
                    'source.username',
                ))
                || blank(data_get(
                    $config,
                    'source.password',
                ))
                || blank(data_get(
                    $config,
                    'destination.host',
                ))
                || blank(data_get(
                    $config,
                    'destination.username',
                ))
                || blank(data_get(
                    $config,
                    'destination.password',
                ))
            ) {
                return false;
            }
        }

        return true;
    }

    public function selectedDatabaseCount(
        PlatformMigration $migration,
    ): int {
        $migration->loadMissing('servers');

        return $migration->servers
            ->where('selected', true)
            ->sum(function ($server) {
                return collect(
                    $server->database_plan ?? []
                )
                    ->where('selected', true)
                    ->count();
            });
    }

    private function testConnection(
        string $host,
        int $port,
        string $username,
        string $password,
        string $label,
    ): void {
        try {
            $pdo = new PDO(
                sprintf(
                    'mysql:host=%s;port=%d;charset=utf8mb4',
                    $host,
                    $port,
                ),
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ],
            );

            $pdo->query('SELECT 1')->fetchColumn();
        } catch (PDOException $exception) {
            throw new RuntimeException(
                "Could not connect to the {$label} database host {$host}:{$port}: "
                . $exception->getMessage(),
                previous: $exception,
            );
        }
    }

    private function hostKey(
        string $host,
        int $port,
    ): string {
        return sha1(
            mb_strtolower(trim($host))
            . ':'
            . $port
        );
    }
}