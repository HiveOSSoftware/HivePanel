<?php

namespace App\Http\Controllers\Cells;

use App\Enums\AuditEvent;
use App\Services\AuditLogger;
use App\Services\Node\CellNodeClient;
use App\Services\Node\ImporterNodeClient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CellImporterController extends CellBaseController
{
    public function index(string $id, CellNodeClient $cells)
    {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }
        $workerCell = $this->getCellOrFail($cell, $cells);

        return Inertia::render('Cells/Importer', [
            'cell' => $workerCell,
        ]);
    }

    public function test(
        string $id,
        Request $request,
        ImporterNodeClient $importer,
        AuditLogger $audit
    ) {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        $data = $request->validate([
            'protocol' => ['required', 'string', 'in:sftp,ftp,ftps'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remote_path' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $importer->testImporter($cell, $data);

        $audit->log(
            AuditEvent::IMPORT_TESTED,
            $cell,
            'Importer connection test executed.',
            [
                'protocol' => $data['protocol'],
                'host' => $data['host'],
                'port' => $data['port'],
                'remote_path' => $data['remote_path'] ?? null,
                'success' => !($result['error'] ?? false),
            ]
        );

        return response()->json($result);
    }

    public function start(
        string $id,
        Request $request,
        CellNodeClient $cells,
        ImporterNodeClient $importer,
        AuditLogger $audit
    ) {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        $this->abortIfLocked($cell, $cells);

        $data = $request->validate([
            'protocol' => ['required', 'string', 'in:sftp,ftp,ftps'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remote_path' => ['nullable', 'string', 'max:1000'],
            'options' => ['nullable', 'array'],
        ]);

        $result = $importer->startImporter($cell, $data);

        $audit->log(
            AuditEvent::IMPORT_STARTED,
            $cell,
            'Server import started.',
            [
                'protocol' => $data['protocol'],
                'host' => $data['host'],
                'port' => $data['port'],
                'remote_path' => $data['remote_path'] ?? null,
                'options' => $data['options'] ?? [],
            ]
        );

        return response()->json($result);
    }

    public function status(string $id, ImporterNodeClient $importer)
    {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        return response()->json(
            $importer->importerStatus($cell)
        );
    }
}