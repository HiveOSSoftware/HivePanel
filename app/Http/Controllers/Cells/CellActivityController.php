<?php

namespace App\Http\Controllers\Cells;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CellActivityController extends CellBaseController
{
    public function index(string $id)
    {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        return Inertia::render('Cells/Activity', [
            'cell' => [
                'id' => $cell->id,
                'name' => $cell->name,
                'comb' => $cell->comb,
                'node' => [
                    'id' => $cell->node?->id,
                    'name' => $cell->node?->name,
                    'location' => $cell->node?->location,
                ],
            ],
            'logs' => $this->logs($cell->id),
        ]);
    }

    public function json(string $id)
    {
        $cell = $this->panelCellOrFail($id);
        $this->abortUnlessInstalled($cell);

        return response()->json([
            'logs' => $this->logs($cell->id),
        ]);
    }

    private function logs(string $cellId)
    {
        return AuditLog::query()
            ->with('user:id,name,email')
            ->where('cell_id', $cellId)
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'event' => $log->event,
                'description' => $log->description,
                'metadata' => $log->metadata,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at?->toISOString(),
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
            ]);
    }
}