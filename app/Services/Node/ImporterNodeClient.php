<?php

namespace App\Services\Node;

use App\Models\Cell;

class ImporterNodeClient
{
    public function __construct(
        private NodeClient $nodeClient
    ) {}

    public function testImporter(Cell $cell, array $data): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->post("/cells/{$cell->daemon_id}/importer/test", $data)
            ->throw()
            ->json();
    }

    public function startImporter(Cell $cell, array $data): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->post("/cells/{$cell->daemon_id}/importer/start", $data)
            ->throw()
            ->json();
    }

    public function importerStatus(Cell $cell): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->get("/cells/{$cell->daemon_id}/importer/status")
            ->throw()
            ->json();
    }
}