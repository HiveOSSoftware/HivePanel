<?php

namespace App\Http\Controllers\Cells;

use App\Services\Node\CellNodeClient;
use App\Enums\AuditEvent;
use App\Services\AuditLogger;

class CellPowerController extends CellBaseController
{
    public function start(string $id, CellNodeClient $cells, AuditLogger $audit)
    {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        $this->abortIfLocked($cell, $cells);

        $cells->startCell($cell);

        $audit->log(
            AuditEvent::SERVER_STARTED,
            $cell,
            'Server was started.'
        );

        return redirect()->route('cells.index');
    }

    public function stop(string $id, CellNodeClient $cells, AuditLogger $audit)
    {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        $this->abortIfLocked($cell, $cells);

        $cells->stopCell($cell);

        $audit->log(
            AuditEvent::SERVER_STOPPED,
            $cell,
            'Server was stopped.'
        );

        return redirect()->route('cells.index');
    }
}