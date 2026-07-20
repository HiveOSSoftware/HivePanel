<?php

namespace App\Http\Controllers\Cells;

use App\Enums\AuditEvent;
use App\Services\AuditLogger;
use App\Services\Node\CellNodeClient;
use Illuminate\Http\Request;

class CellConsoleController extends CellBaseController
{
    public function statsJson(string $id, CellNodeClient $cells)
    {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        try {
            return response()->json($cells->stats($cell));
        } catch (\Throwable $e) {
            return response()->json([
                'cpu' => 0,
                'memory_mb' => 0,
                'disk_bytes' => 0,
                'uptime_sec' => 0,
                'network_rx_bytes' => 0,
                'network_tx_bytes' => 0,
                'error' => 'stats unavailable',
                'details' => $e->getMessage(),
            ]);
        }
    }

    public function consoleJson(string $id, CellNodeClient $cells)
    {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        return response()->json($cells->console($cell));
    }

    public function command(string $id, Request $request, CellNodeClient $cells, AuditLogger $audit)
    {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        $this->abortIfLocked($cell, $cells);

        $data = $request->validate([
            'command' => ['required', 'string'],
        ]);

        $result = $cells->sendCommand($cell, $data['command']);

        $audit->log(
            AuditEvent::CONSOLE_COMMAND,
            $cell,
            'Console command was sent.',
            [
                'command' => $data['command'],
            ]
        );

        return response()->json($result);
    }

    public function consoleSession(String $id, CellNodeClient $cells)
    {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }
        
        $session = $cells->createConsoleSession($cell);

        return response()->json([
            'ws_url' => str($cell->node->scheme)
                ->replace('http', 'ws')
                ."://{$cell->node->fqdn}:{$cell->node->port}/cells/{$cell->daemon_id}/ws?token={$session['token']}",
            'expires_in' => $session['expires_in'] ?? 30,
        ]);
    }
}