<?php

namespace App\Services\Migrations\Sources;

use PDO;
use PDOException;
use RuntimeException;

class PterodactylDatabaseSource
{
    private ?PDO $connection = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $database,
        private readonly string $username,
        private readonly string $password,
    ) {
    }

    public function testConnection(): void
    {
        $pdo = $this->pdo();

        $pdo->query('SELECT 1')->fetchColumn();

        if (! $this->tableExists('users')) {
            throw new RuntimeException(
                "Connected to the database, but the 'users' table was not found. Check that this is the Pterodactyl panel database."
            );
        }

        if (! $this->tableExists('servers')) {
            throw new RuntimeException(
                "Connected to the database, but the 'servers' table was not found. Check that this is the Pterodactyl panel database."
            );
        }
    }

    public function users(): array
    {
        $columns = $this->columns('users');

        if (! in_array('email', $columns, true)) {
            throw new RuntimeException(
                "The Pterodactyl users table does not contain an 'email' column."
            );
        }

        $select = [
            $this->columnOrNull($columns, 'id'),
            'email',
            $this->columnOrNull($columns, 'username'),
            $this->columnOrNull($columns, 'name_first'),
            $this->columnOrNull($columns, 'name_last'),
            $this->columnOrNull($columns, 'password'),
        ];

        $statement = $this->pdo()->query(
            'SELECT ' . implode(', ', $select) . ' FROM users'
        );

        $result = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $email = mb_strtolower(trim((string) ($row['email'] ?? '')));

            if ($email === '') {
                continue;
            }

            $passwordHash = isset($row['password'])
                ? (string) $row['password']
                : '';

            $result[sha1($email)] = [
                'id' => $row['id'] ?? null,
                'email' => $email,
                'username' => $row['username'] ?? null,
                'first_name' => $row['name_first'] ?? null,
                'last_name' => $row['name_last'] ?? null,
                'password_hash' => $passwordHash !== ''
                    ? $passwordHash
                    : null,
                'password_hash_type' => $this->hashType($passwordHash),
                'password_compatible' => $this->isHashCompatible(
                    $passwordHash
                ),
            ];
        }

        return $result;
    }

    public function serverDatabases(): array
    {
        if (! $this->tableExists('databases')) {
            return [];
        }

        $columns = $this->columns('databases');

        if (! in_array('server_id', $columns, true)) {
            return [];
        }

        $select = [
            $this->columnOrNull($columns, 'id'),
            'server_id',
            $this->columnOrNull($columns, 'database_host_id'),
            $this->columnOrNull($columns, 'database'),
            $this->columnOrNull($columns, 'username'),
            $this->columnOrNull($columns, 'remote'),
            $this->columnOrNull($columns, 'max_connections'),
        ];

        $statement = $this->pdo()->query(
            'SELECT ' . implode(', ', $select) . ' FROM `databases`'
        );

        $hosts = $this->databaseHosts();
        $result = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $serverId = isset($row['server_id'])
                ? (string) $row['server_id']
                : null;

            if (! $serverId) {
                continue;
            }

            $hostId = isset($row['database_host_id'])
                ? (string) $row['database_host_id']
                : null;

            $host = $hostId
                ? ($hosts[$hostId] ?? null)
                : null;

            $result[$serverId] ??= [];

            $result[$serverId][] = [
                'id' => $row['id'] ?? null,
                'database' => $row['database'] ?? null,
                'username' => $row['username'] ?? null,
                'remote' => $row['remote'] ?? null,
                'max_connections' => isset($row['max_connections'])
                    ? (int) $row['max_connections']
                    : null,
                'host' => $host,
            ];
        }

        return $result;
    }

    private function databaseHosts(): array
    {
        if (! $this->tableExists('database_hosts')) {
            return [];
        }

        $columns = $this->columns('database_hosts');

        $select = [
            $this->columnOrNull($columns, 'id'),
            $this->columnOrNull($columns, 'name'),
            $this->columnOrNull($columns, 'host'),
            $this->columnOrNull($columns, 'port'),
        ];

        $statement = $this->pdo()->query(
            'SELECT ' . implode(', ', $select) . ' FROM `database_hosts`'
        );

        $result = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (! isset($row['id'])) {
                continue;
            }

            $result[(string) $row['id']] = [
                'id' => $row['id'],
                'name' => $row['name'] ?? null,
                'host' => $row['host'] ?? null,
                'port' => isset($row['port'])
                    ? (int) $row['port']
                    : 3306,
            ];
        }

        return $result;
    }

    private function pdo(): PDO
    {
        if ($this->connection) {
            return $this->connection;
        }

        try {
            $this->connection = new PDO(
                sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                    $this->host,
                    $this->port,
                    $this->database,
                ),
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 5,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ],
            );
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'Could not connect to the Pterodactyl database: '
                . $exception->getMessage(),
                previous: $exception,
            );
        }

        return $this->connection;
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?'
        );

        $statement->execute([
            $this->database,
            $table,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function columns(string $table): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT column_name FROM information_schema.columns WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position'
        );

        $statement->execute([
            $this->database,
            $table,
        ]);

        return array_map(
            fn (array $row) => (string) $row['column_name'],
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    private function columnOrNull(
        array $columns,
        string $column,
    ): string {
        if (in_array($column, $columns, true)) {
            return "`{$column}`";
        }

        return "NULL AS `{$column}`";
    }

    private function hashType(string $hash): ?string
    {
        if ($hash === '') {
            return null;
        }

        if (
            str_starts_with($hash, '$2y$')
            || str_starts_with($hash, '$2b$')
            || str_starts_with($hash, '$2a$')
        ) {
            return 'bcrypt';
        }

        if (str_starts_with($hash, '$argon2id$')) {
            return 'argon2id';
        }

        if (str_starts_with($hash, '$argon2i$')) {
            return 'argon2i';
        }

        return 'unknown';
    }

    private function isHashCompatible(string $hash): bool
    {
        $type = $this->hashType($hash);

        if (! $type) {
            return false;
        }

        $driver = (string) config(
            'hashing.driver',
            'bcrypt',
        );

        if ($driver === 'bcrypt') {
            return $type === 'bcrypt';
        }

        if (
            $driver === 'argon'
            || $driver === 'argon2id'
        ) {
            return in_array(
                $type,
                [
                    'argon2id',
                    'argon2i',
                ],
                true
            );
        }

        return false;
    }
}