<?php

namespace App\Services\Migrations;

use App\Models\PlatformMigration;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MigrationTransferConfigurationService
{
    public function update(
        PlatformMigration $migration,
        array $nodes,
    ): array {
        $sourceConfig = $migration->source_config ?? [];
        $existing = $sourceConfig['transfer_nodes'] ?? [];

        foreach ($nodes as $sourceNode => $config) {
            $sourceNode = trim((string) $sourceNode);

            if ($sourceNode === '') {
                continue;
            }

            $current = $existing[$sourceNode] ?? [];

            $protocol = strtolower(trim((string) (
                $config['protocol']
                ?? $current['protocol']
                ?? 'sftp'
            )));

            if (! in_array($protocol, ['sftp', 'local'], true)) {
                throw ValidationException::withMessages([
                    'transfer' => "Invalid transfer method for source node {$sourceNode}.",
                ]);
            }

            $pathTemplate = trim((string) (
                $config['path_template']
                ?? $current['path_template']
                ?? '/var/lib/pterodactyl/volumes/{uuid}'
            ));

            if ($pathTemplate === '' || ! str_contains($pathTemplate, '{uuid}')) {
                throw ValidationException::withMessages([
                    'transfer' => "The volume path template for source node {$sourceNode} must contain {uuid}.",
                ]);
            }

            if ($protocol === 'local') {
                $destinationNodeIds = $migration->servers()
                    ->where('selected', true)
                    ->where('source_node_name', $sourceNode)
                    ->pluck('destination_node_id')
                    ->filter()
                    ->unique()
                    ->values();

                if ($destinationNodeIds->count() !== 1) {
                    throw ValidationException::withMessages([
                        'transfer' => "In-place migration for source node {$sourceNode} requires all selected servers from that source node to map to exactly one HivePanel destination Node.",
                    ]);
                }

                if (! (bool) ($config['same_machine_confirmed'] ?? false)) {
                    throw ValidationException::withMessages([
                        'transfer' => "Confirm that the mapped HivePanel Worker for {$sourceNode} is installed on the same physical machine.",
                    ]);
                }

                if (! (bool) ($config['source_servers_stopped_confirmed'] ?? false)) {
                    throw ValidationException::withMessages([
                        'transfer' => "Confirm that the selected Pterodactyl servers on {$sourceNode} are stopped before using in-place migration.",
                    ]);
                }

                $existing[$sourceNode] = [
                    'protocol' => 'local',
                    'host' => '',
                    'port' => 0,
                    'username' => '',
                    'auth_type' => 'none',
                    'password' => null,
                    'private_key' => null,
                    'private_key_passphrase' => null,
                    'public_key' => null,
                    'path_template' => $pathTemplate,
                    'path_detected' => (bool) ($current['path_detected'] ?? false),
                    'path_detected_at' => $current['path_detected_at'] ?? null,
                    'same_machine_confirmed' => true,
                    'source_servers_stopped_confirmed' => true,
                    'file_strategy' => 'copy',
                ];

                continue;
            }

            $authType = (string) (
                $config['auth_type']
                ?? $current['auth_type']
                ?? 'password'
            );

            if (! in_array($authType, ['password', 'private_key'], true)) {
                throw ValidationException::withMessages([
                    'transfer' => "Invalid authentication type for source node {$sourceNode}.",
                ]);
            }

            $password = filled($config['password'] ?? null)
                ? (string) $config['password']
                : ($current['password'] ?? null);

            $privateKey = filled($config['private_key'] ?? null)
                ? (string) $config['private_key']
                : ($current['private_key'] ?? null);

            $privateKeyPassphrase = filled($config['private_key_passphrase'] ?? null)
                ? (string) $config['private_key_passphrase']
                : ($current['private_key_passphrase'] ?? null);

            if ($authType === 'password' && blank($password)) {
                throw ValidationException::withMessages([
                    'transfer' => "A password is required for source node {$sourceNode}.",
                ]);
            }

            if ($authType === 'private_key' && blank($privateKey)) {
                throw ValidationException::withMessages([
                    'transfer' => "An SSH private key is required for source node {$sourceNode}. Generate one or paste an existing key.",
                ]);
            }

            $existing[$sourceNode] = [
                'protocol' => 'sftp',
                'host' => trim((string) ($config['host'] ?? '')),
                'port' => (int) ($config['port'] ?? 22),
                'username' => trim((string) ($config['username'] ?? '')),
                'auth_type' => $authType,
                'password' => $authType === 'password' ? $password : null,
                'private_key' => $authType === 'private_key' ? $privateKey : null,
                'private_key_passphrase' => $authType === 'private_key'
                    ? $privateKeyPassphrase
                    : null,
                'public_key' => $current['public_key'] ?? null,
                'path_template' => $pathTemplate,
                'path_detected' => isset($current['path_template'])
                    && $pathTemplate === trim((string) $current['path_template'])
                    ? (bool) ($current['path_detected'] ?? false)
                    : false,
                'path_detected_at' => isset($current['path_template'])
                    && $pathTemplate === trim((string) $current['path_template'])
                    ? ($current['path_detected_at'] ?? null)
                    : null,
                'same_machine_confirmed' => false,
                'source_servers_stopped_confirmed' => false,
                'file_strategy' => 'copy',
            ];
        }

        $sourceConfig['transfer_nodes'] = $existing;

        $migration->forceFill([
            'source_config' => $sourceConfig,
        ])->save();

        $detected = [];

        foreach ($existing as $sourceNode => $config) {
            if (
                ($config['protocol'] ?? 'sftp') !== 'sftp'
                || ($config['auth_type'] ?? 'password') !== 'private_key'
                || blank($config['private_key'] ?? null)
            ) {
                continue;
            }

            try {
                $pathTemplate = $this->detectWingsVolumePath($config);
            } catch (\Throwable $exception) {
                report($exception);
                continue;
            }

            if (blank($pathTemplate)) {
                continue;
            }

            $existing[$sourceNode]['path_template'] = $pathTemplate;
            $existing[$sourceNode]['path_detected'] = true;
            $existing[$sourceNode]['path_detected_at'] = now()->toISOString();
            $detected[$sourceNode] = $pathTemplate;
        }

        if ($detected !== []) {
            $sourceConfig['transfer_nodes'] = $existing;

            $migration->forceFill([
                'source_config' => $sourceConfig,
            ])->save();
        }

        return [
            'detected_paths' => $detected,
        ];
    }

    public function generateKey(
        PlatformMigration $migration,
        string $sourceNode,
    ): array {
        $sourceNode = trim($sourceNode);

        if ($sourceNode === '') {
            throw ValidationException::withMessages([
                'transfer' => 'A source node is required.',
            ]);
        }

        $directory = storage_path(
            'app/migrations/ssh/'
            . $migration->id
            . '/'
            . Str::slug($sourceNode)
            . '-'
            . Str::random(10)
        );

        if (! is_dir($directory) && ! mkdir(
            $directory,
            0700,
            true,
        ) && ! is_dir($directory)) {
            throw new RuntimeException(
                'Could not create a temporary SSH key directory.'
            );
        }

        $privateKeyPath = $directory . '/id_ed25519';
        $comment = 'hivepanel-migration-'
            . $migration->id
            . '-'
            . Str::slug($sourceNode);

        try {
            $result = Process::timeout(15)->run([
                'ssh-keygen',
                '-q',
                '-t',
                'ed25519',
                '-N',
                '',
                '-C',
                $comment,
                '-f',
                $privateKeyPath,
            ]);

            if ($result->failed()) {
                throw new RuntimeException(
                    'Failed to generate SSH key: '
                    . trim(
                        $result->errorOutput()
                        ?: $result->output()
                    )
                );
            }

            $privateKey = file_get_contents(
                $privateKeyPath
            );

            $publicKey = file_get_contents(
                $privateKeyPath . '.pub'
            );

            if (
                $privateKey === false
                || $publicKey === false
            ) {
                throw new RuntimeException(
                    'The generated SSH key could not be read.'
                );
            }

            $sourceConfig = $migration->source_config ?? [];
            $existing = $sourceConfig['transfer_nodes'] ?? [];
            $current = $existing[$sourceNode] ?? [];

            $existing[$sourceNode] = [
                ...$current,
                'protocol' => $current['protocol'] ?? 'sftp',
                'host' => $current['host'] ?? '',
                'port' => (int) ($current['port'] ?? 22),
                'username' => $current['username']
                    ?? 'hivepanel-migration',
                'auth_type' => 'private_key',
                'password' => null,
                'private_key' => trim($privateKey) . "\n",
                'private_key_passphrase' => null,
                'public_key' => trim($publicKey),
                'path_template' => $current['path_template']
                    ?? '/var/lib/pterodactyl/volumes/{uuid}',
            ];

            $sourceConfig['transfer_nodes'] = $existing;

            $migration->forceFill([
                'source_config' => $sourceConfig,
            ])->save();

            return [
                'public_key' => trim($publicKey),
                'setup_command' => $this->setupCommand(
                    trim($publicKey)
                ),
            ];
        } finally {
            @unlink($privateKeyPath);
            @unlink($privateKeyPath . '.pub');
            @rmdir($directory);
        }
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
                $protocol = $config['protocol'] ?? 'sftp';

                return [
                    'source_node' => $sourceNode,
                    'protocol' => $protocol,
                    'host' => $config['host'] ?? '',
                    'port' => (int) ($config['port'] ?? 22),
                    'username' => $config['username'] ?? 'hivepanel-migration',
                    'auth_type' => $config['auth_type'] ?? (
                        filled($config['private_key'] ?? null)
                            ? 'private_key'
                            : 'password'
                    ),
                    'path_template' => $config['path_template']
                        ?? '/var/lib/pterodactyl/volumes/{uuid}',
                    'path_detected' => (bool) ($config['path_detected'] ?? false),
                    'path_detected_at' => $config['path_detected_at'] ?? null,
                    'same_machine_confirmed' => (bool) (
                        $config['same_machine_confirmed'] ?? false
                    ),
                    'source_servers_stopped_confirmed' => (bool) (
                        $config['source_servers_stopped_confirmed'] ?? false
                    ),
                    'file_strategy' => $config['file_strategy'] ?? 'copy',
                    'has_password' => filled($config['password'] ?? null),
                    'has_private_key' => filled($config['private_key'] ?? null),
                    'public_key' => $config['public_key'] ?? null,
                    'setup_command' => (
                        $protocol === 'sftp'
                        && filled($config['public_key'] ?? null)
                    )
                        ? $this->setupCommand((string) $config['public_key'])
                        : null,
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

            if (! is_array($config)) {
                return false;
            }

            $protocol = strtolower(trim((string) ($config['protocol'] ?? 'sftp')));
            $pathTemplate = trim((string) ($config['path_template'] ?? ''));

            if ($pathTemplate === '' || ! str_contains($pathTemplate, '{uuid}')) {
                return false;
            }

            if ($protocol === 'local') {
                if (
                    ! (bool) ($config['same_machine_confirmed'] ?? false)
                    || ! (bool) ($config['source_servers_stopped_confirmed'] ?? false)
                ) {
                    return false;
                }

                continue;
            }

            if ($protocol !== 'sftp') {
                return false;
            }

            $authType = (string) (
                $config['auth_type']
                ?? (
                    filled($config['private_key'] ?? null)
                        ? 'private_key'
                        : 'password'
                )
            );

            $hasAuth = match ($authType) {
                'private_key' => filled($config['private_key'] ?? null),
                default => filled($config['password'] ?? null),
            };

            if (
                blank($config['host'] ?? null)
                || blank($config['username'] ?? null)
                || ! $hasAuth
            ) {
                return false;
            }
        }

        return true;
    }

    private function setupCommand(
        string $publicKey,
    ): string {
        $encodedKey = base64_encode(
            trim($publicKey)
        );

        return implode(' && ', [
            'command -v setfacl >/dev/null || { echo "setfacl is required; install the acl package first."; exit 1; }',
            'id hivepanel-migration >/dev/null 2>&1 || sudo useradd --create-home --shell /bin/bash hivepanel-migration',
            'sudo install -d -m 700 -o hivepanel-migration -g hivepanel-migration /home/hivepanel-migration/.ssh',
            'echo ' . escapeshellarg($encodedKey) . ' | base64 -d | sed \'s/^/restrict,command="internal-sftp" /\' | sudo tee /home/hivepanel-migration/.ssh/authorized_keys >/dev/null',
            'sudo chown hivepanel-migration:hivepanel-migration /home/hivepanel-migration/.ssh/authorized_keys',
            'sudo chmod 600 /home/hivepanel-migration/.ssh/authorized_keys',
            'sudo setfacl -m u:hivepanel-migration:--x /etc/pterodactyl',
            'sudo setfacl -m u:hivepanel-migration:r-- /etc/pterodactyl/config.yml',
            'DATA_PATH="$(sudo awk \'/^system:/{in_system=1; next} in_system && /^[^[:space:]]/{in_system=0} in_system && /^[[:space:]]+data:[[:space:]]*/{sub(/^[[:space:]]+data:[[:space:]]*/, ""); gsub(/[\\047\\042]/, ""); print; exit}\' /etc/pterodactyl/config.yml)"',
            'test -n "$DATA_PATH" || { echo "Could not detect Wings system.data from /etc/pterodactyl/config.yml"; exit 1; }',
            'PARENT="$DATA_PATH"; while [ "$PARENT" != "/" ]; do PARENT="$(dirname "$PARENT")"; [ "$PARENT" = "/" ] && break; sudo setfacl -m u:hivepanel-migration:--x "$PARENT"; done',
            'sudo setfacl -m u:hivepanel-migration:r-x "$DATA_PATH"',
            'sudo setfacl -Rm u:hivepanel-migration:rX "$DATA_PATH"',
            'sudo setfacl -Rdm u:hivepanel-migration:rX "$DATA_PATH"',
            'echo "HivePanel migration access configured successfully."',
            'echo "Detected Pterodactyl volume template: ${DATA_PATH%/}/{uuid}"',
        ]);
    }

    private function detectWingsVolumePath(
        array $config,
    ): ?string {
        $privateKey = (string) (
            $config['private_key']
            ?? ''
        );

        if (trim($privateKey) === '') {
            return null;
        }

        $directory = storage_path(
            'app/migrations/ssh-detect/'
            . Str::random(20)
        );

        if (! is_dir($directory) && ! mkdir(
            $directory,
            0700,
            true,
        ) && ! is_dir($directory)) {
            throw new RuntimeException(
                'Could not create a temporary Wings detection directory.'
            );
        }

        $privateKeyPath = $directory . '/id_ed25519';
        $configPath = $directory . '/config.yml';
        $batchPath = $directory . '/sftp.batch';

        try {
            if (file_put_contents(
                $privateKeyPath,
                $privateKey,
            ) === false) {
                throw new RuntimeException(
                    'Could not write the temporary migration SSH key.'
                );
            }

            chmod($privateKeyPath, 0600);

            $remoteConfig = '/etc/pterodactyl/config.yml';

            if (file_put_contents(
                $batchPath,
                'get '
                . escapeshellarg($remoteConfig)
                . ' '
                . escapeshellarg($configPath)
                . PHP_EOL
                . 'bye'
                . PHP_EOL,
            ) === false) {
                throw new RuntimeException(
                    'Could not create the temporary SFTP batch file.'
                );
            }

            $result = Process::timeout(20)->run([
                'sftp',
                '-q',
                '-b',
                $batchPath,
                '-i',
                $privateKeyPath,
                '-P',
                (string) (
                    (int) (
                        $config['port']
                        ?? 22
                    )
                ),
                '-o',
                'StrictHostKeyChecking=no',
                '-o',
                'UserKnownHostsFile=/dev/null',
                '-o',
                'IdentitiesOnly=yes',
                trim(
                    (string) (
                        $config['username']
                        ?? ''
                    )
                )
                . '@'
                . trim(
                    (string) (
                        $config['host']
                        ?? ''
                    )
                ),
            ]);

            if ($result->failed()) {
                throw new RuntimeException(
                    'HivePanel connected to the source node but could not read the Wings configuration for automatic volume-path detection. '
                    . trim(
                        $result->errorOutput()
                        ?: $result->output()
                    )
                );
            }

            $contents = file_get_contents(
                $configPath,
            );

            if ($contents === false) {
                return null;
            }

            $dataPath = $this->parseWingsDataPath(
                $contents,
            );

            if ($dataPath === null) {
                return null;
            }

            return rtrim(
                $dataPath,
                '/',
            ) . '/{uuid}';
        } finally {
            @unlink($privateKeyPath);
            @unlink($configPath);
            @unlink($batchPath);
            @rmdir($directory);
        }
    }

    private function parseWingsDataPath(
        string $yaml,
    ): ?string {
        $inSystem = false;

        foreach (preg_split(
            '/\\R/',
            $yaml,
        ) ?: [] as $line) {
            if (preg_match(
                '/^system:\\s*$/',
                $line,
            )) {
                $inSystem = true;
                continue;
            }

            if (
                $inSystem
                && preg_match(
                    '/^[^\\s#]/',
                    $line,
                )
            ) {
                break;
            }

            if (
                ! $inSystem
                || ! preg_match(
                    '/^\\s+data:\\s*(.+?)\\s*$/',
                    $line,
                    $matches,
                )
            ) {
                continue;
            }

            $value = trim(
                $matches[1],
            );

            $value = trim(
                $value,
                "'\\\"",
            );

            return $value !== ''
                ? $value
                : null;
        }

        return null;
    }
}