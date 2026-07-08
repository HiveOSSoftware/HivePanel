<?php

namespace App\Http\Controllers\Cells;

use App\Enums\AuditEvent;
use App\Services\AuditLogger;
use App\Services\Node\CellNodeClient;
use App\Services\Node\PlayerNodeClient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CellPlayerController extends CellBaseController
{
    public function index(string $id, CellNodeClient $cells)
    {
        $cell = $this->panelCellOrFail($id);
        $workerCell = $this->getCellOrFail($cell, $cells);

        return Inertia::render('Cells/Players', [
            'cell' => $workerCell,
        ]);
    }

    public function json(string $id, PlayerNodeClient $players)
    {
        $cell = $this->panelCellOrFail($id);

        return response()->json(
            $players->players($cell)
        );
    }

    public function action(
        string $id,
        string $action,
        Request $request,
        CellNodeClient $cells,
        PlayerNodeClient $players,
        AuditLogger $audit
    ) {
        $cell = $this->panelCellOrFail($id);

        $this->abortIfLocked($cell, $cells);

        abort_unless(in_array($action, ['kick', 'ban', 'op', 'deop'], true), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:16'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $players->playerAction(
            $cell,
            $action,
            $data['name'],
            $data['reason'] ?? null
        );

        $audit->log(
            match ($action) {
                'kick' => AuditEvent::PLAYER_KICKED,
                'ban' => AuditEvent::PLAYER_BANNED,
                'op' => AuditEvent::PLAYER_OPPED,
                'deop' => AuditEvent::PLAYER_DEOPPED,
            },
            $cell,
            "Player \"{$data['name']}\" was {$action}.",
            [
                'player' => $data['name'],
                'action' => $action,
                'reason' => $data['reason'] ?? null,
            ]
        );

        return response()->json($result);
    }
}