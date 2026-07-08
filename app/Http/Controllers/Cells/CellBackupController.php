<?php

namespace App\Http\Controllers\Cells;

use App\Enums\AuditEvent;
use App\Services\AuditLogger;
use App\Services\Node\BackupNodeClient;
use App\Services\Node\CellNodeClient;
use Inertia\Inertia;

class CellBackupController extends CellBaseController
{
    public function index(string $id, CellNodeClient $cells)
    {
        $cell = $this->panelCellOrFail($id);
        $workerCell = $this->getCellOrFail($cell, $cells);

        return Inertia::render('Cells/Backups', [
            'cell' => $workerCell,
        ]);
    }

    public function json(string $id, BackupNodeClient $backups)
    {
        $cell = $this->panelCellOrFail($id);

        return response()->json(
            $backups->backups($cell)
        );
    }

    public function create(string $id, CellNodeClient $cells, BackupNodeClient $backups, AuditLogger $audit)
    {
        $cell = $this->panelCellOrFail($id);

        $this->abortIfLocked($cell, $cells);

        $result = $backups->createBackup($cell);

        $audit->log(
            AuditEvent::BACKUP_CREATED,
            $cell,
            'Backup was created.',
            ['result' => $result]
        );

        return response()->json($result);
    }

    public function delete(string $id, string $name, CellNodeClient $cells, BackupNodeClient $backups, AuditLogger $audit)
    {
        $cell = $this->panelCellOrFail($id);

        $this->abortIfLocked($cell, $cells);

        $result = $backups->deleteBackup($cell, $name);

        $audit->log(
            AuditEvent::BACKUP_DELETED,
            $cell,
            "Backup \"{$name}\" was deleted.",
            ['backup' => $name]
        );

        return response()->json($result);
    }

    public function restore(string $id, string $name, CellNodeClient $cells, BackupNodeClient $backups, AuditLogger $audit)
    {
        $cell = $this->panelCellOrFail($id);

        $this->abortIfLocked($cell, $cells);

        $result = $backups->restoreBackup($cell, $name);

        $audit->log(
            AuditEvent::BACKUP_RESTORED,
            $cell,
            "Backup \"{$name}\" was restored.",
            ['backup' => $name]
        );

        return response()->json($result);
    }

    public function download(string $id, string $name, BackupNodeClient $backups, AuditLogger $audit)
    {
        $cell = $this->panelCellOrFail($id);

        $audit->log(
            AuditEvent::BACKUP_DOWNLOADED,
            $cell,
            "Backup \"{$name}\" was downloaded.",
            ['backup' => $name]
        );

        return $backups->downloadBackup($cell, $name);
    }
}