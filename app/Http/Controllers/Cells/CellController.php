<?php

namespace App\Http\Controllers\Cells;

use App\Enums\AuditEvent;
use App\Models\Cell;
use App\Models\Node;
use App\Services\AuditLogger;
use App\Services\Node\CellNodeClient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CellController extends CellBaseController
{
    public function index(CellNodeClient $cells)
    {
        $panelCells = Cell::query()
            ->visibleTo(request()->user())
            ->with('node')
            ->latest()
            ->get();

        return Inertia::render('Dashboard', [
            'cells' => $panelCells
                ->map(fn (Cell $cell) => $this->frontendCell($cell, $cells))
                ->values(),
            'combs' => [],
        ]);
    }

    public function show(string $id, CellNodeClient $cells)
    {
        $cell = $this->panelCellOrFail($id);
        $workerCell = $this->getCellOrFail($cell, $cells);

        return Inertia::render('Cells/Show', [
            'cell' => $workerCell,
            'stats' => [
                'cpu' => 0,
                'memory_mb' => 0,
                'disk_bytes' => 0,
                'uptime_sec' => 0,
                'network_rx_bytes' => 0,
                'network_tx_bytes' => 0,
            ],
            'console_ws_url' => $cell->node
                ? "{$cell->node->scheme}://{$cell->node->fqdn}:{$cell->node->port}/cells/{$cell->daemon_id}/ws?token={$cell->node->api_token}"
                : null,
        ]);
    }
}