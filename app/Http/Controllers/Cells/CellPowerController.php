<?php

namespace App\Http\Controllers\Cells;

use App\Enums\AuditEvent;
use App\Enums\CellInstallStatus;
use App\Services\AuditLogger;
use App\Services\Node\CellNodeClient;
use Symfony\Component\HttpFoundation\Response;

class CellPowerController extends CellBaseController
{
    public function start(
        string $id,
        CellNodeClient $cells,
        AuditLogger $audit,
    ) {
        $cell = $this->panelCellOrFail($id);

        $this->abortUnlessInstalled($cell);

        $this->abortIfLocked(
            $cell,
            $cells,
        );

        $workerCell = $this->getCellOrFail(
            $cell,
            $cells,
        );

        $status = strtolower(
            (string) ($workerCell['status'] ?? ''),
        );

        if (
            in_array(
                $status,
                [
                    'starting',
                    'running',
                ],
                true,
            )
        ) {
            abort(
                Response::HTTP_CONFLICT,
                $status === 'running'
                    ? 'This server is already running.'
                    : 'This server is already starting.',
            );
        }

        $cells->startCell($cell);

        $audit->log(
            AuditEvent::SERVER_STARTED,
            $cell,
            'Server was started.',
        );

        return redirect()->route('cells.index');
    }

    public function stop(
        string $id,
        CellNodeClient $cells,
        AuditLogger $audit,
    ) {
        $cell = $this->panelCellOrFail($id);

        $this->abortUnlessInstalled($cell);

        $this->abortIfLocked(
            $cell,
            $cells,
        );

        $workerCell = $this->getCellOrFail(
            $cell,
            $cells,
        );

        $status = strtolower(
            (string) ($workerCell['status'] ?? ''),
        );

        if (
            in_array(
                $status,
                [
                    'offline',
                    'stopping',
                ],
                true,
            )
        ) {
            abort(
                Response::HTTP_CONFLICT,
                $status === 'offline'
                    ? 'This server is already stopped.'
                    : 'This server is already stopping.',
            );
        }

        $cells->stopCell($cell);

        $audit->log(
            AuditEvent::SERVER_STOPPED,
            $cell,
            'Server was stopped.',
        );

        return redirect()->route('cells.index');
    }
}