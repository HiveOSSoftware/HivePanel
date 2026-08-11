<?php

namespace App\Services\Node;

use App\Models\Cell;
use App\Models\Node;
use Illuminate\Http\Client\ConnectionException;

class CellNodeClient
{
    public function __construct(private NodeClient $nodeClient) {
    }

    public function cells(Node $node): array
    {
        return $this->nodeClient->client($node)->get('/cells')->throw()->json();
    }

    public function cell(Cell $cell): array
    {
        try {
            return $this->nodeClient->client($cell->node)->get('/cells/' . rawurlencode($cell->daemon_id))->throw()->json();
        } catch (ConnectionException) {
            return [
                'error' => true,
                'message' => 'Node worker is not reachable or timed out.',
            ];
        }
    }

    public function createCell(Node $node, array $data): array
    {
        return $this->nodeClient->client($node)->post('/cells', $data)->throw()->json();
    }

    public function startCell(Cell $cell): array
    {
        return $this->nodeClient->client($cell->node)->post('/cells/' . rawurlencode($cell->daemon_id) . '/start')->throw()->json();
    }

    public function stopCell(Cell $cell): array
    {
        return $this->nodeClient->client($cell->node)->post('/cells/' . rawurlencode($cell->daemon_id) . '/stop')->throw()->json();
    }

    public function deleteCell(Cell $cell): array
    {
        return $this->nodeClient->client($cell->node)->delete('/cells/' . rawurlencode($cell->daemon_id))->throw()->json();
    }

    public function stats(Cell $cell): array
    {
        return $this->nodeClient->client($cell->node)->get('/cells/' . rawurlencode($cell->daemon_id) . '/stats')->throw()->json();
    }

    public function console(Cell $cell): array
    {
        return $this->nodeClient->client($cell->node)->get('/cells/' . rawurlencode($cell->daemon_id) . '/console')->throw()->json();
    }

    public function createConsoleSession(Cell $cell): array
    {
        return $this->nodeClient->client($cell->node)->post('/cells/' . rawurlencode($cell->daemon_id) . '/console-session')->throw()->json();
    }

    public function sendCommand(Cell $cell, string $command): array
    {
        return $this->nodeClient->client($cell->node)->post('/cells/' . rawurlencode($cell->daemon_id) . '/command', [
            'command' => $command,
        ])->throw()->json();
    }

    public function updateCellSettings(Cell $cell, array $data): array
    {
        return $this->nodeClient->client($cell->node)->patch('/cells/' . rawurlencode($cell->daemon_id) . '/settings', $data)->throw()->json();
    }

    public function runUtility(Cell $cell, string $utility): array
    {
        return $this->nodeClient->client($cell->node)->post('/cells/' . rawurlencode($cell->daemon_id) . '/utilities/' . rawurlencode($utility))->throw()->json();
    }

    public function installCell(Cell $cell): array
    {
        return $this->nodeClient->client($cell->node)->post('/cells/' . rawurlencode($cell->daemon_id) . '/install')->throw()->json();
    }

    public function startCellByDaemonId(Node $node, string $daemonId): array
    {
        return $this->nodeClient->client($node)->post('/cells/' . rawurlencode($daemonId) . '/start')->throw()->json();
    }

    public function prepareReinstall(Cell $cell): array
    {
        return $this->nodeClient->client($cell->node)->post('/cells/' . rawurlencode($cell->daemon_id) . '/reinstall')->throw()->json();
    }

    public function updateCellDefinition(Cell $cell): array
    {
        return $this->nodeClient->client($cell->node)->patch('/cells/' . rawurlencode($cell->daemon_id) . '/definition', $this->definitionPayload($cell))->throw()->json();
    }

    public function cellForSync(Cell $cell): array
    {
        try {
            $response = $this->nodeClient->client($cell->node)->get('/cells/' . rawurlencode($cell->daemon_id));

            if ($response->status() === 404) {
                return [
                    'exists' => false,
                    'reachable' => true,
                    'cell' => null,
                ];
            }

            $response->throw();

            return [
                'exists' => true,
                'reachable' => true,
                'cell' => $response->json(),
            ];
        } catch (ConnectionException) {
            return [
                'exists' => false,
                'reachable' => false,
                'cell' => null,
            ];
        }
    }

    public function definitionPayload(Cell $cell): array
    {
        $cell->loadMissing('allocation');

        return [
            'name' => $cell->name,
            'comb' => $cell->comb,
            'comb_data' => $cell->comb_data,
            'variables' => $cell->variables,
            'allocation' => $cell->allocation ? [
                'ip' => $cell->allocation->ip,
                'port' => $cell->allocation->port,
            ] : null,
            'limits' => [
                'memory_mb' => (int) data_get($cell->limits, 'memory_mb', 1024),
                'cpu_percent' => (int) data_get($cell->limits, 'cpu_percent', 100),
                'disk_mb' => (int) data_get($cell->limits, 'disk_mb', 0),
            ],
        ];
    }
}