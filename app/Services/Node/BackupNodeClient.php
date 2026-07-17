<?php

namespace App\Services\Node;

use App\Models\Backup;
use App\Models\BackupMount;
use App\Models\Cell;
use Illuminate\Http\Client\Response;

class BackupNodeClient
{
    public function __construct(
        private readonly NodeClient $nodeClient,
    ) {
    }

    public function backups(Cell $cell): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->get(
                "/cells/{$this->cellID($cell)}/backups",
            )
            ->throw()
            ->json();
    }

    public function createBackup(
        Cell $cell,
        Backup $backup,
    ): array {
        return $this->nodeClient
            ->client($cell->node)
            ->post(
                "/cells/{$this->cellID($cell)}/backups",
                [
                    'backup_id' => $backup->id,
                    'name' => $backup->name,
                    'ignored_files' => $backup->ignored_files ?? [],
                ],
            )
            ->throw()
            ->json();
    }

    public function deleteBackup(
        Cell $cell,
        Backup $backup,
    ): array {
        return $this->nodeClient
            ->client($cell->node)
            ->delete(
                "/cells/{$this->cellID($cell)}/backups/"
                .rawurlencode($backup->id),
            )
            ->throw()
            ->json();
    }

    public function restoreBackup(
        Cell $cell,
        Backup $backup,
    ): array {
        return $this->nodeClient
            ->client($cell->node)
            ->post(
                "/cells/{$this->cellID($cell)}/backups/"
                .rawurlencode($backup->id)
                .'/restore',
            )
            ->throw()
            ->json();
    }

    public function downloadBackup(
        Cell $cell,
        Backup $backup,
    ): Response {
        return $this->nodeClient
            ->client($cell->node)
            ->withOptions([
                'stream' => true,
            ])
            ->get(
                "/cells/{$this->cellID($cell)}/backups/"
                .rawurlencode($backup->id)
                .'/download',
            )
            ->throw();
    }

    public function mountBackup(
        Cell $cell,
        Backup $backup,
        BackupMount $mount,
    ): array {
        return $this->nodeClient
            ->client($cell->node)
            ->post(
                "/cells/{$this->cellID($cell)}/backups/"
                .rawurlencode($backup->id)
                .'/mount',
                [
                    'mount_id' => $mount->id,
                ],
            )
            ->throw()
            ->json();
    }

    public function unmountBackup(
        Cell $cell,
        BackupMount $mount,
    ): array {
        return $this->nodeClient
            ->client($cell->node)
            ->delete(
                "/cells/{$this->cellID($cell)}/backup-mounts/"
                .rawurlencode($mount->id),
            )
            ->throw()
            ->json();
    }

    public function mountedBackupFiles(
        Cell $cell,
        BackupMount $mount,
        string $path = '',
        int $page = 1,
        int $perPage = 250,
    ): array {
        return $this->nodeClient
            ->client($cell->node)
            ->get(
                "/cells/{$this->cellID($cell)}/backup-mounts/"
                .rawurlencode($mount->id)
                .'/files',
                [
                    'path' => $path,
                    'page' => max(1, $page),
                    'per_page' => min(
                        500,
                        max(1, $perPage),
                    ),
                ],
            )
            ->throw()
            ->json();
    }

    private function cellID(Cell $cell): string
    {
        return rawurlencode($cell->daemon_id);
    }
}