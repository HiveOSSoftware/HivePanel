<?php

namespace App\Services\Node;

use App\Models\Cell;
use App\Models\Node;
use Illuminate\Http\Client\ConnectionException;

class CellNodeClient
{
    public function __construct(
        private NodeClient $nodeClient
    ) {}

    public function cells(Node $node): array
    {
        return $this->nodeClient
            ->client($node)
            ->get('/cells')
            ->throw()
            ->json();
    }

    public function cell(Cell $cell): array
    {
        try {
            return $this->nodeClient
                ->client($cell->node)
                ->get("/cells/{$cell->daemon_id}")
                ->throw()
                ->json();
        } catch (ConnectionException) {
            return [
                'error' => true,
                'message' => 'Node worker is not reachable or timed out.',
            ];
        }
    }

    public function createCell(Node $node, array $data): array
    {
        return $this->nodeClient
            ->client($node)
            ->post('/cells', $data)
            ->throw()
            ->json();
    }

    public function startCell(Cell $cell): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->post("/cells/{$cell->daemon_id}/start")
            ->throw()
            ->json();
    }

    public function stopCell(Cell $cell): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->post("/cells/{$cell->daemon_id}/stop")
            ->throw()
            ->json();
    }

    public function deleteCell(Cell $cell): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->delete("/cells/{$cell->daemon_id}")
            ->throw()
            ->json();
    }

    public function stats(Cell $cell): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->get("/cells/{$cell->daemon_id}/stats")
            ->throw()
            ->json();
    }

    public function console(Cell $cell): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->get("/cells/{$cell->daemon_id}/console")
            ->throw()
            ->json();
    }

    public function createConsoleSession(Cell $cell): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->post("/cells/{$cell->daemon_id}/console-session")
            ->throw()
            ->json();
    }

    public function sendCommand(Cell $cell, string $command): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->post("/cells/{$cell->daemon_id}/command", [
                'command' => $command,
            ])
            ->throw()
            ->json();
    }

    public function updateCellSettings(Cell $cell, array $data): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->patch("/cells/{$cell->daemon_id}/settings", $data)
            ->throw()
            ->json();
    }

    public function runUtility(Cell $cell, string $utility): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->post("/cells/{$cell->daemon_id}/utilities/{$utility}")
            ->throw()
            ->json();
    }

    public function installCell(Cell $cell): array
    {
        return $this->nodeClient
            ->client($cell->node)
            ->post("/cells/{$cell->daemon_id}/install")
            ->throw()
            ->json();
    }

    public function startCellByDaemonId(Node $node, string $daemonId): array
    {
        return $this->nodeClient
            ->client($node)
            ->post("/cells/{$daemonId}/start")
            ->throw()
            ->json();
    }
}