<?php

namespace App\Http\Controllers\Cells;

use App\Enums\CellInstallStatus;
use App\Models\Comb;
use App\Services\Cells\CellReinstallService;
use App\Services\Node\CellNodeClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class CellReinstallController extends CellBaseController
{
    public function show(
        string $id,
        CellNodeClient $cells,
    ): Response {
        $cell = $this->panelCellOrFail($id);

        $workerCell = $this->frontendCell(
            $cell,
            $cells,
        );

        return Inertia::render('Cells/Reinstall', [
            'cell' => $workerCell,

            'combs' => Comb::query()
                ->orderBy('game')
                ->orderBy('name')
                ->get()
                ->map(fn (Comb $comb) => [
                    'id' => $comb->id,
                    'external_id' => $comb->external_id,
                    'name' => $comb->name,
                    'game' => $comb->game,
                    'source' => $comb->source,
                    'data' => $comb->data,
                ])
                ->values(),
        ]);
    }

    public function store(
        Request $request,
        string $id,
        CellNodeClient $cells,
        CellReinstallService $reinstall,
    ): JsonResponse {
        $cell = $this->panelCellOrFail($id);

        $workerCell = $cells->cell($cell);

        if (($workerCell['error'] ?? false) === true) {
            throw new RuntimeException(
                $workerCell['message']
                    ?? 'The node could not be contacted.',
            );
        }

        if ($this->workerCellIsRunning($workerCell)) {
            return response()->json([
                'message' => 'Stop the cell before reinstalling.',
            ], 409);
        }

        $validated = $request->validate([
            'comb_id' => [
                'required',
                'exists:combs,id',
            ],

            'variables' => [
                'nullable',
                'array',
            ],

            'variables.*' => [
                'nullable',
            ],

            'start_on_completion' => [
                'sometimes',
                'boolean',
            ],

            'confirmation' => [
                'required',
                'string',
            ],
        ]);

        if (
            trim($validated['confirmation'])
            !== $cell->name
        ) {
            return response()->json([
                'message' =>
                    'Enter the cell name exactly to confirm the reinstall.',
                'errors' => [
                    'confirmation' => [
                        'The confirmation does not match the cell name.',
                    ],
                ],
            ], 422);
        }

        $comb = Comb::query()
            ->findOrFail(
                $validated['comb_id'],
            );

        try {
            $updatedCell = $reinstall->reinstall(
                cell: $cell,
                comb: $comb,
                variables: $validated['variables'] ?? [],
                startAfterInstall: (bool) (
                    $validated['start_on_completion']
                    ?? false
                ),
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage()
                    ?: 'The cell could not be reinstalled.',
            ], 500);
        }

        return response()->json([
            'success' => true,

            'message' =>
                'The cell reinstall has been queued.',

            'cell' => [
                'id' => $updatedCell->id,
                'install_status' =>
                    $updatedCell->install_status->value,
            ],
        ]);
    }

    public function retry(
        Request $request,
        string $id,
        CellNodeClient $cells,
        CellReinstallService $reinstall,
    ): JsonResponse {
        $cell = $this->panelCellOrFail($id);

        if (
            $cell->install_status
            !== CellInstallStatus::FAILED
        ) {
            return response()->json([
                'message' =>
                    'Only failed installations can be retried.',
            ], 409);
        }

        $workerCell = $cells->cell($cell);

        if (($workerCell['error'] ?? false) === true) {
            return response()->json([
                'message' => $workerCell['message']
                    ?? 'The node could not be contacted.',
            ], 502);
        }

        if ($this->workerCellIsRunning($workerCell)) {
            return response()->json([
                'message' => 'Stop the cell before retrying installation.',
            ], 409);
        }

        $validated = $request->validate([
            'start_on_completion' => [
                'sometimes',
                'boolean',
            ],
        ]);

        try {
            $updatedCell = $reinstall->retry(
                cell: $cell,
                startAfterInstall: (bool) (
                    $validated['start_on_completion']
                    ?? false
                ),
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage()
                    ?: 'The installation could not be retried.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Installation has been queued again.',

            'cell' => [
                'id' => $updatedCell->id,
                'install_status' =>
                    $updatedCell->install_status->value,
            ],
        ]);
    }

    public function installationStatus(string $id)
    {
        $cell = $this->panelCellOrFail($id);

        return response()->json([
            'install_status' => $cell->install_status?->value
                ?? (string) $cell->install_status,

            'install_status_label' => $cell->install_status?->label()
                ?? ucfirst((string) $cell->install_status),

            'install_failure_reason' => $cell->install_failure_reason,

            'installed_at' => $cell->installed_at?->toIso8601String(),
        ]);
    }

    private function workerCellIsRunning(
        array $workerCell,
    ): bool {
        if (($workerCell['running'] ?? false) === true) {
            return true;
        }

        return strtolower(
            (string) ($workerCell['status'] ?? ''),
        ) === 'running';
    }
}