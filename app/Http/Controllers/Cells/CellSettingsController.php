<?php

namespace App\Http\Controllers\Cells;

use App\Enums\AuditEvent;
use App\Services\AuditLogger;
use App\Services\Node\CellNodeClient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CellSettingsController extends CellBaseController
{
    public function index(string $id, CellNodeClient $cells)
    {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }
        $workerCell = $this->getCellOrFail($cell, $cells);

        return Inertia::render('Cells/Settings', [
            'cell' => $workerCell,
        ]);
    }

    public function update(string $id, Request $request, CellNodeClient $cells, AuditLogger $audit)
    {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        $this->abortIfLocked($cell, $cells);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'jarfile' => ['required', 'string', 'max:255'],
            'world_name' => ['required', 'string', 'max:255'],
        ]);

        $result = $cells->updateCellSettings($cell, $data);

        $audit->log(
            AuditEvent::SETTINGS_UPDATED,
            $cell,
            'Server settings were updated.',
            [
                'fields' => array_keys($data),
            ]
        );

        return response()->json($result);
    }

    public function utility(string $id, string $utility, CellNodeClient $cells, AuditLogger $audit)
    {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        $this->abortIfLocked($cell, $cells);

        abort_unless(in_array($utility, [
            'reset-files',
            'reset-world',
            'repair-permissions',
            'clear-logs',
        ], true), 404);

        $result = $cells->runUtility($cell, $utility);

        $audit->log(
            AuditEvent::SETTINGS_UPDATED,
            $cell,
            "Utility \"{$utility}\" was run.",
            [
                'utility' => $utility,
            ]
        );

        return response()->json($result);
    }
}