<?php

namespace App\Http\Controllers\Cells;

use App\Enums\AuditEvent;
use App\Services\AuditLogger;
use App\Services\Node\CellNodeClient;
use App\Services\Node\FileNodeClient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CellConfigController extends CellBaseController
{
    public function index(string $id, CellNodeClient $cells)
    {
        $cell = $this->panelCellOrFail($id);
        $workerCell = $this->getCellOrFail($cell, $cells);

        return Inertia::render('Cells/Config', [
            'cell' => $workerCell,
            'configFiles' => $this->configFiles(),
        ]);
    }

    public function json(string $id, Request $request, FileNodeClient $files)
    {
        $cell = $this->panelCellOrFail($id);

        return response()->json(
            $files->readFile($cell, $request->query('path', 'server.properties'))
        );
    }

    public function update(
        string $id,
        Request $request,
        CellNodeClient $cells,
        FileNodeClient $files,
        AuditLogger $audit
    ) {
        $cell = $this->panelCellOrFail($id);

        $this->abortIfLocked($cell, $cells);

        $data = $request->validate([
            'path' => ['required', 'string'],
            'content' => ['present', 'string'],
        ]);

        $result = $files->writeFile(
            $cell,
            $data['path'],
            $data['content']
        );

        $audit->log(
            AuditEvent::CONFIG_UPDATED,
            $cell,
            "Updated configuration file \"{$data['path']}\".",
            [
                'path' => $data['path'],
            ]
        );

        return response()->json($result);
    }

    private function configFiles(): array
    {
        return [
            [
                'id' => 'server.properties',
                'title' => 'Server Properties',
                'path' => 'server.properties',
                'description' => 'Main Minecraft server settings.',
            ],
            [
                'id' => 'spigot.yml',
                'title' => 'Spigot Configuration',
                'path' => 'spigot.yml',
                'description' => 'Performance and gameplay settings.',
            ],
            [
                'id' => 'bukkit.yml',
                'title' => 'Bukkit Configuration',
                'path' => 'bukkit.yml',
                'description' => 'Plugin and world settings.',
            ],
            [
                'id' => 'paper-global.yml',
                'title' => 'Paper Global Settings',
                'path' => 'config/paper-global.yml',
                'description' => 'Global Paper server settings.',
            ],
            [
                'id' => 'paper-world-defaults.yml',
                'title' => 'Paper World Defaults',
                'path' => 'config/paper-world-defaults.yml',
                'description' => 'Default world settings for Paper.',
            ],
        ];
    }
}