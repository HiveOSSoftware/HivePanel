<?php

namespace App\Services\Node;

use App\Models\Cell;
use Illuminate\Http\Client\Response;

class BackupNodeClient
{
    public function __construct(
        private NodeClient $nodeClient
    ) {}

    public function backups(Cell $cell): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->get("/cells/{$cell->daemon_id}/backups")
            ->throw()
            ->json();
    }

    public function createBackup(Cell $cell): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->post("/cells/{$cell->daemon_id}/backups")
            ->throw()
            ->json();
    }

    public function deleteBackup(Cell $cell, string $name): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->delete("/cells/{$cell->daemon_id}/backups/" . urlencode($name))
            ->throw()
            ->json();
    }

    public function restoreBackup(Cell $cell, string $name): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->post("/cells/{$cell->daemon_id}/backups/" . urlencode($name) . "/restore")
            ->throw()
            ->json();
    }

    public function downloadBackup(Cell $cell, string $name): Response
    {
        return $this->nodeClient
            ->client($cell->node)
            ->get("/cells/{$cell->daemon_id}/backups/" . urlencode($name) . "/download")
            ->throw();
    }
}