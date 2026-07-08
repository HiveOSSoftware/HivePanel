<?php

namespace App\Services\Node;

use App\Models\Cell;

class PlayerNodeClient
{
    public function __construct(
        private NodeClient $nodeClient
    ) {}

    public function players(Cell $cell): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->get("/cells/{$cell->daemon_id}/players")
            ->throw()
            ->json();
    }

    public function playerAction(Cell $cell, string $action, string $name, ?string $reason = null): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->post("/cells/{$cell->daemon_id}/players/{$action}", [
                'name' => $name,
                'reason' => $reason,
            ])
            ->throw()
            ->json();
    }
}