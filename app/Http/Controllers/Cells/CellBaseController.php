<?php

namespace App\Http\Controllers\Cells;

use App\Http\Controllers\Controller;
use App\Models\Cell;
use App\Services\Node\CellNodeClient;
use Inertia\Inertia;

abstract class CellBaseController extends Controller
{
    protected function panelCellOrFail(string $id): Cell
    {
        return Cell::query()
            ->with('node')
            ->whereKey($id)
            ->where(function ($query) {
                $query->where('owner_id', auth()->id())
                    ->orWhereHas('users', fn ($query) => $query->where('user_id', auth()->id()));
            })
            ->firstOrFail();
    }

    protected function getCellOrFail(Cell $cell, CellNodeClient $cells): array
    {
        $workerCell = $cells->cell($cell);

        if (($workerCell['error'] ?? false) === true) {
            abort(502, $workerCell['message'] ?? 'Node unavailable');
        }

        return $this->mergePanelCell($cell, $workerCell);
    }

    protected function frontendCell(Cell $cell, CellNodeClient $cells): array
    {
        $workerCell = $cells->cell($cell);

        if (($workerCell['error'] ?? false) === true) {
            return $this->mergePanelCell($cell, [
                'status' => 'offline',
                'error' => true,
                'message' => $workerCell['message'] ?? 'Node unavailable',
            ]);
        }

        return $this->mergePanelCell($cell, $workerCell);
    }

    protected function mergePanelCell(Cell $cell, array $workerCell): array
    {
        return [
            ...$workerCell,
            'id' => $cell->id,
            'daemon_id' => $cell->daemon_id,
            'node_id' => $cell->node_id,
            'name' => $workerCell['name'] ?? $cell->name,
            'comb' => $workerCell['comb'] ?? $cell->comb,
            'node' => [
                'id' => $cell->node?->id,
                'name' => $cell->node?->name,
                'location' => $cell->node?->location,
            ],
        ];
    }

    protected function isLocked(array $cell): bool
    {
        return ($cell['lock']['locked'] ?? false) === true;
    }

    protected function lockedPage(array $cell)
    {
        return Inertia::render('Cells/Locked', [
            'cell' => $cell,
        ])->toResponse(request())->setStatusCode(423);
    }

    protected function abortIfLocked(Cell $cell, CellNodeClient $cells): void
    {
        $workerCell = $this->getCellOrFail($cell, $cells);

        if ($this->isLocked($workerCell)) {
            abort(423, $workerCell['lock']['message'] ?? 'Server is locked.');
        }
    }
}