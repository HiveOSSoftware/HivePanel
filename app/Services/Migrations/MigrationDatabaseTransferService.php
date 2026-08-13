<?php

namespace App\Services\Migrations;

use App\Models\PlatformMigration;
use App\Models\PlatformMigrationServer;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class MigrationDatabaseTransferService
{
    private const DATABASE_NAME_MAX_LENGTH = 64;
    private const USERNAME_MAX_LENGTH = 32;

    public function transferServer(
        PlatformMigrationServer $server,
    ): array {
        $server->loadMissing('migration');

        $migration = $server->migration;

        if (! $migration) {
            throw new RuntimeException(
                'Migration record is missing.'
            );
        }

        if (! in_array(
            $server->status,
            [
                'database_pending',
                'database_failed',
                'database_transferring',
            ],
            true
        )) {
            throw new RuntimeException(
                "Server '{$server->name}' is not waiting for database transfer."
            );
        }

        $plan = array_values(
            (array) (
                $server->database_plan
                ?? []
            )
        );

        $selectedIndexes = collect($plan)
            ->keys()
            ->filter(
                fn (int $index) =>
                    (bool) (
                        $plan[$index]['selected']
                        ?? false
                    )
            )
            ->values();

        if ($selectedIndexes->isEmpty()) {
            $server->forceFill([
                'status' => 'completed',
                'current_stage' => 'Migration complete',
                'progress' => 100,
                'error' => null,
                'completed_at' => now(),
            ])->save();

            return [
                'completed' => 0,
                'failed' => 0,
            ];
        }

        $dumpBinary = $this->resolveBinary([
            'mariadb-dump',
            'mysqldump',
        ]);

        $clientBinary = $this->resolveBinary([
            'mariadb',
            'mysql',
        ]);

        $total = $selectedIndexes->count();
        $completed = 0;
        $failed = 0;

        foreach (
            $selectedIndexes as $position => $index
        ) {
            $database = (array) $plan[$index];

            if (
                ($database['status'] ?? null)
                === 'completed'
            ) {
                $completed++;
                continue;
            }

            $sourceDatabase = trim(
                (string) data_get(
                    $database,
                    'source.database',
                    '',
                )
            );

            $label = $sourceDatabase !== ''
                ? $sourceDatabase
                : 'database ' . ($position + 1);

            $plan[$index]['status'] = 'transferring';
            $plan[$index]['error'] = null;
            $plan[$index]['started_at'] = now()
                ->toISOString();

            $server->forceFill([
                'database_plan' => $plan,
                'status' => 'database_transferring',
                'current_stage' =>
                    "Transferring database {$label} ("
                    . ($position + 1)
                    . "/{$total})",
                'progress' => 85 + (int) floor(
                    ($position / max(1, $total)) * 14
                ),
                'error' => null,
            ])->save();

            try {
                $destination = $this->transferDatabase(
                    migration: $migration,
                    server: $server,
                    database: $database,
                    index: $index,
                    dumpBinary: $dumpBinary,
                    clientBinary: $clientBinary,
                );

                $plan[$index]['status'] = 'completed';
                $plan[$index]['destination'] = $destination;
                $plan[$index]['error'] = null;
                $plan[$index]['completed_at'] = now()
                    ->toISOString();

                $completed++;
            } catch (Throwable $exception) {
                report($exception);

                $plan[$index]['status'] = 'failed';
                $plan[$index]['error'] =
                    $exception->getMessage()
                    ?: 'Database transfer failed.';
                $plan[$index]['completed_at'] = null;

                $failed++;
            }

            $server->forceFill([
                'database_plan' => $plan,
                'progress' => 85 + (int) floor(
                    (($position + 1) / max(1, $total))
                    * 14
                ),
            ])->save();
        }

        if ($failed > 0) {
            $server->forceFill([
                'database_plan' => $plan,
                'status' => 'database_failed',
                'current_stage' =>
                    "{$failed} database transfer(s) failed",
                'progress' => 99,
                'error' =>
                    "{$failed} selected database(s) failed to transfer. "
                    . 'Retrying will only rerun failed databases.',
                'completed_at' => null,
            ])->save();

            return [
                'completed' => $completed,
                'failed' => $failed,
            ];
        }

        $server->forceFill([
            'database_plan' => $plan,
            'status' => 'completed',
            'current_stage' => 'Migration complete',
            'progress' => 100,
            'error' => null,
            'completed_at' => now(),
        ])->save();

        return [
            'completed' => $completed,
            'failed' => 0,
        ];
    }

    public function resetFailedDatabases(
        PlatformMigrationServer $server,
    ): int {
        $plan = array_values(
            (array) (
                $server->database_plan
                ?? []
            )
        );

        $reset = 0;

        foreach ($plan as $index => $database) {
            if (
                ! (bool) (
                    $database['selected']
                    ?? false
                )
                || ($database['status'] ?? null)
                    !== 'failed'
            ) {
                continue;
            }

            $plan[$index]['status'] = 'pending';
            $plan[$index]['error'] = null;
            $plan[$index]['started_at'] = null;
            $plan[$index]['completed_at'] = null;

            $reset++;
        }

        if ($reset === 0) {
            return 0;
        }

        $server->forceFill([
            'database_plan' => $plan,
            'status' => 'database_pending',
            'current_stage' => 'Database retry queued',
            'progress' => 85,
            'error' => null,
            'completed_at' => null,
        ])->save();

        return $reset;
    }

    private function transferDatabase(
        PlatformMigration $migration,
        PlatformMigrationServer $server,
        array $database,
        int $index,
        string $dumpBinary,
        string $clientBinary,
    ): array {
        $source = (array) (
            $database['source']
            ?? []
        );

        $sourceHost = (array) (
            $source['host']
            ?? []
        );

        $sourceDatabase = trim(
            (string) (
                $source['database']
                ?? ''
            )
        );

        $host = trim(
            (string) (
                $sourceHost['host']
                ?? ''
            )
        );

        $port = (int) (
            $sourceHost['port']
            ?? 3306
        );

        if (
            $sourceDatabase === ''
            || $host === ''
        ) {
            throw new RuntimeException(
                'The discovered source database definition is incomplete.'
            );
        }

        $hostConfig = $this->hostConfiguration(
            $migration,
            $host,
            $port,
        );

        $sourceConfig = (array) (
            $hostConfig['source']
            ?? []
        );

        $destinationConfig = (array) (
            $hostConfig['destination']
            ?? []
        );

        $destination = (array) (
            $database['destination']
            ?? []
        );

        $credentialKey = trim(
            (string) (
                $destination['credential_key']
                ?? ''
            )
        );

        if ($credentialKey === '') {
            $credentialKey = $this->credentialKey(
                $server,
                $source,
                $index,
            );
        }

        $credentials = $this->databaseCredentials(
            $migration,
            $credentialKey,
        );

        $destinationDatabase = trim(
            (string) (
                $destination['database']
                ?? ''
            )
        );

        $destinationUsername = trim(
            (string) (
                $destination['username']
                ?? ''
            )
        );

        if ($destinationDatabase === '') {
            $destinationDatabase =
                $this->destinationDatabaseName(
                    $migration,
                    $server,
                    $sourceDatabase,
                    $destinationConfig,
                );
        }

        if ($destinationUsername === '') {
            $destinationUsername =
                $this->destinationUsername(
                    $server,
                    $sourceDatabase,
                    $index,
                );
        }

        $destinationPassword = (string) (
            $credentials['password']
            ?? ''
        );

        if ($destinationPassword === '') {
            $destinationPassword = Str::password(
                length: 32,
                letters: true,
                numbers: true,
                symbols: false,
                spaces: false,
            );
        }

        $this->saveDatabaseCredentials(
            $migration,
            $credentialKey,
            [
                'password' => $destinationPassword,
                'created_at' => $credentials['created_at']
                    ?? now()->toISOString(),
            ],
        );

        $sourcePdo = $this->pdo(
            host: (string) (
                $sourceConfig['host']
                ?? $host
            ),
            port: (int) (
                $sourceConfig['port']
                ?? $port
            ),
            username: (string) (
                $sourceConfig['username']
                ?? ''
            ),
            password: (string) (
                $sourceConfig['password']
                ?? ''
            ),
        );

        $destinationPdo = $this->pdo(
            host: (string) (
                $destinationConfig['host']
                ?? ''
            ),
            port: (int) (
                $destinationConfig['port']
                ?? 3306
            ),
            username: (string) (
                $destinationConfig['username']
                ?? ''
            ),
            password: (string) (
                $destinationConfig['password']
                ?? ''
            ),
        );

        [
            $charset,
            $collation,
        ] = $this->sourceCharsetAndCollation(
            $sourcePdo,
            $sourceDatabase,
        );

        $this->prepareDestination(
            pdo: $destinationPdo,
            database: $destinationDatabase,
            username: $destinationUsername,
            password: $destinationPassword,
            charset: $charset,
            collation: $collation,
        );

        $directory = storage_path(
            'app/migrations/database-dumps/'
            . $migration->id
            . '/'
            . $server->id
        );

        if (
            ! is_dir($directory)
            && ! mkdir(
                $directory,
                0700,
                true,
            )
            && ! is_dir($directory)
        ) {
            throw new RuntimeException(
                'Could not create the temporary database dump directory.'
            );
        }

        $dumpPath = $directory
            . '/'
            . hash(
                'sha256',
                $credentialKey,
            )
            . '.sql';

        try {
            $this->dumpDatabase(
                binary: $dumpBinary,
                host: (string) (
                    $sourceConfig['host']
                    ?? $host
                ),
                port: (int) (
                    $sourceConfig['port']
                    ?? $port
                ),
                username: (string) (
                    $sourceConfig['username']
                    ?? ''
                ),
                password: (string) (
                    $sourceConfig['password']
                    ?? ''
                ),
                database: $sourceDatabase,
                dumpPath: $dumpPath,
            );

            $this->importDatabase(
                binary: $clientBinary,
                host: (string) (
                    $destinationConfig['host']
                    ?? ''
                ),
                port: (int) (
                    $destinationConfig['port']
                    ?? 3306
                ),
                username: (string) (
                    $destinationConfig['username']
                    ?? ''
                ),
                password: (string) (
                    $destinationConfig['password']
                    ?? ''
                ),
                database: $destinationDatabase,
                dumpPath: $dumpPath,
            );
        } finally {
            @unlink($dumpPath);
            @rmdir($directory);
        }

        return [
            'host' => (string) (
                $destinationConfig['host']
                ?? ''
            ),
            'port' => (int) (
                $destinationConfig['port']
                ?? 3306
            ),
            'database' => $destinationDatabase,
            'username' => $destinationUsername,
            'remote' => '%',
            'credential_key' => $credentialKey,
            'charset' => $charset,
            'collation' => $collation,
        ];
    }

    private function prepareDestination(
        PDO $pdo,
        string $database,
        string $username,
        string $password,
        string $charset,
        string $collation,
    ): void {
        $databaseIdentifier = $this->quoteIdentifier(
            $database
        );

        $charset = preg_match(
            '/^[A-Za-z0-9_]+$/',
            $charset,
        )
            ? $charset
            : 'utf8mb4';

        $collation = preg_match(
            '/^[A-Za-z0-9_]+$/',
            $collation,
        )
            ? $collation
            : 'utf8mb4_unicode_ci';

        $quotedUsername = $pdo->quote(
            $username
        );

        $quotedRemote = $pdo->quote('%');

        $quotedPassword = $pdo->quote(
            $password
        );

        $pdo->exec(
            "DROP DATABASE IF EXISTS {$databaseIdentifier}"
        );

        $pdo->exec(
            "CREATE DATABASE {$databaseIdentifier} "
            . "CHARACTER SET {$charset} "
            . "COLLATE {$collation}"
        );

        $pdo->exec(
            "CREATE USER IF NOT EXISTS {$quotedUsername}@{$quotedRemote} "
            . "IDENTIFIED BY {$quotedPassword}"
        );

        $pdo->exec(
            "ALTER USER {$quotedUsername}@{$quotedRemote} "
            . "IDENTIFIED BY {$quotedPassword}"
        );

        $pdo->exec(
            "GRANT ALL PRIVILEGES ON {$databaseIdentifier}.* "
            . "TO {$quotedUsername}@{$quotedRemote}"
        );
    }

    private function sourceCharsetAndCollation(
        PDO $pdo,
        string $database,
    ): array {
        $statement = $pdo->prepare(
            'SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME '
            . 'FROM information_schema.SCHEMATA '
            . 'WHERE SCHEMA_NAME = ?'
        );

        $statement->execute([
            $database,
        ]);

        $row = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return [
            (string) (
                $row['DEFAULT_CHARACTER_SET_NAME']
                ?? 'utf8mb4'
            ),
            (string) (
                $row['DEFAULT_COLLATION_NAME']
                ?? 'utf8mb4_unicode_ci'
            ),
        ];
    }

    private function dumpDatabase(
        string $binary,
        string $host,
        int $port,
        string $username,
        string $password,
        string $database,
        string $dumpPath,
    ): void {
        $process = new Process(
            [
                $binary,
                '--protocol=tcp',
                '--host=' . $host,
                '--port=' . $port,
                '--user=' . $username,
                '--single-transaction',
                '--quick',
                '--skip-lock-tables',
                '--routines',
                '--triggers',
                '--events',
                '--hex-blob',
                '--default-character-set=utf8mb4',
                '--result-file=' . $dumpPath,
                $database,
            ],
            null,
            [
                'MYSQL_PWD' => $password,
            ],
        );

        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                "Could not export source database '{$database}': "
                . trim(
                    $process->getErrorOutput()
                    ?: $process->getOutput()
                )
            );
        }

        if (
            ! is_file($dumpPath)
            || filesize($dumpPath) === 0
        ) {
            throw new RuntimeException(
                "The source database dump for '{$database}' was empty."
            );
        }
    }

    private function importDatabase(
        string $binary,
        string $host,
        int $port,
        string $username,
        string $password,
        string $database,
        string $dumpPath,
    ): void {
        $stream = fopen(
            $dumpPath,
            'rb',
        );

        if ($stream === false) {
            throw new RuntimeException(
                'Could not open the temporary database dump.'
            );
        }

        try {
            $process = new Process(
                [
                    $binary,
                    '--protocol=tcp',
                    '--host=' . $host,
                    '--port=' . $port,
                    '--user=' . $username,
                    '--default-character-set=utf8mb4',
                    '--database=' . $database,
                ],
                null,
                [
                    'MYSQL_PWD' => $password,
                ],
            );

            $process->setInput($stream);
            $process->setTimeout(3600);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(
                    "Could not import destination database '{$database}': "
                    . trim(
                        $process->getErrorOutput()
                        ?: $process->getOutput()
                    )
                );
            }
        } finally {
            fclose($stream);
        }
    }

    private function pdo(
        string $host,
        int $port,
        string $username,
        string $password,
    ): PDO {
        if (
            trim($host) === ''
            || trim($username) === ''
            || $password === ''
        ) {
            throw new RuntimeException(
                'Database host credentials are incomplete.'
            );
        }

        return new PDO(
            sprintf(
                'mysql:host=%s;port=%d;charset=utf8mb4',
                $host,
                $port,
            ),
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 10,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
    }

    private function hostConfiguration(
        PlatformMigration $migration,
        string $host,
        int $port,
    ): array {
        $key = sha1(
            mb_strtolower(
                trim($host)
            )
            . ':'
            . $port
        );

        $config = (array) data_get(
            $migration->execution_config,
            'database_hosts.' . $key,
            [],
        );

        if (
            $config === []
            || ! (bool) (
                $config['verified']
                ?? false
            )
        ) {
            throw new RuntimeException(
                "Database transfer host {$host}:{$port} is not configured or verified."
            );
        }

        return $config;
    }

    private function destinationDatabaseName(
        PlatformMigration $migration,
        PlatformMigrationServer $server,
        string $sourceDatabase,
        array $destinationConfig,
    ): string {
        $prefix = preg_replace(
            '/[^A-Za-z0-9_]+/',
            '_',
            (string) (
                $destinationConfig['prefix']
                ?? 'hive_'
            ),
        ) ?? 'hive_';

        $base = preg_replace(
            '/[^A-Za-z0-9_]+/',
            '_',
            $sourceDatabase,
        ) ?? 'database';

        $suffix = '_'
            . substr(
                sha1(
                    $migration->id
                    . ':'
                    . $server->id
                    . ':'
                    . $sourceDatabase
                ),
                0,
                8,
            );

        return substr(
            $prefix . $base,
            0,
            self::DATABASE_NAME_MAX_LENGTH
            - strlen($suffix),
        ) . $suffix;
    }

    private function destinationUsername(
        PlatformMigrationServer $server,
        string $sourceDatabase,
        int $index,
    ): string {
        $base = 'hive_'
            . substr(
                str_replace(
                    '-',
                    '',
                    (string) $server->id,
                ),
                0,
                8,
            )
            . '_'
            . substr(
                preg_replace(
                    '/[^A-Za-z0-9_]+/',
                    '_',
                    $sourceDatabase,
                ) ?? 'db',
                0,
                12,
            )
            . '_'
            . ($index + 1);

        return substr(
            $base,
            0,
            self::USERNAME_MAX_LENGTH,
        );
    }

    private function credentialKey(
        PlatformMigrationServer $server,
        array $source,
        int $index,
    ): string {
        $sourceId = (string) (
            $source['id']
            ?? $source['database']
            ?? $index
        );

        return $server->id
            . ':'
            . hash(
                'sha256',
                $sourceId,
            );
    }

    private function databaseCredentials(
        PlatformMigration $migration,
        string $credentialKey,
    ): array {
        return (array) data_get(
            $migration->execution_config,
            'database_credentials.'
            . $credentialKey,
            [],
        );
    }

    private function saveDatabaseCredentials(
        PlatformMigration $migration,
        string $credentialKey,
        array $credentials,
    ): void {
        $executionConfig =
            $migration->execution_config
            ?? [];

        data_set(
            $executionConfig,
            'database_credentials.'
            . $credentialKey,
            $credentials,
        );

        $migration->forceFill([
            'execution_config' => $executionConfig,
        ])->save();

        $migration->refresh();
    }

    private function resolveBinary(
        array $candidates,
    ): string {
        foreach ($candidates as $candidate) {
            $process = new Process([
                $candidate,
                '--version',
            ]);

            $process->setTimeout(5);

            try {
                $process->run();
            } catch (Throwable) {
                continue;
            }

            if ($process->isSuccessful()) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'MySQL/MariaDB command-line tools are not installed. '
            . 'Install mariadb-client or mysql-client on the HivePanel web/queue host.'
        );
    }

    private function quoteIdentifier(
        string $identifier,
    ): string {
        if (
            $identifier === ''
            || strlen($identifier)
                > self::DATABASE_NAME_MAX_LENGTH
        ) {
            throw new RuntimeException(
                'The destination database name is invalid.'
            );
        }

        return '`'
            . str_replace(
                '`',
                '``',
                $identifier,
            )
            . '`';
    }
}