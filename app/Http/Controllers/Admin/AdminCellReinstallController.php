<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cell;
use App\Models\Comb;
use App\Services\Cells\CellReinstallService;
use App\Services\Node\CellNodeClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class AdminCellReinstallController extends Controller
{
    public function store(Request $request, string $id, CellNodeClient $cells, CellReinstallService $reinstall): JsonResponse
    {
        $cell = Cell::query()
            ->with([
                'node',
                'allocation',
            ])
            ->findOrFail($id);

        if (! $cell->worker_recovery_required) {
            return response()->json([
                'message' => 'This Cell does not currently require recovery.',
            ], 409);
        }

        if (! $cell->node) {
            return response()->json([
                'message' => 'This Cell is not assigned to a node.',
            ], 409);
        }

        if (! $cell->daemon_id) {
            return response()->json([
                'message' => 'This Cell does not have a daemon ID.',
            ], 409);
        }

        if (! $cell->allocation) {
            return response()->json([
                'message' => 'This Cell does not have a primary allocation.',
            ], 409);
        }

        $workerCell = $cells->cell($cell);

        if (($workerCell['error'] ?? false) === true) {
            return response()->json([
                'message' => $workerCell['message'] ?? 'The node could not be contacted.',
            ], 502);
        }

        if ($this->workerCellIsRunning($workerCell)) {
            return response()->json([
                'message' => 'Stop the Cell before reinstalling.',
            ], 409);
        }

        $validated = $request->validate([
            'confirmation' => [
                'required',
                'string',
            ],
            'start_on_completion' => [
                'sometimes',
                'boolean',
            ],
        ]);

        if (trim($validated['confirmation']) !== $cell->name) {
            return response()->json([
                'message' => 'Enter the Cell name exactly to confirm the reinstall.',
                'errors' => [
                    'confirmation' => [
                        'The confirmation does not match the Cell name.',
                    ],
                ],
            ], 422);
        }

        $combId = data_get($cell->metadata ?? [], 'comb_id');

        if (! $combId) {
            return response()->json([
                'message' => 'HivePanel does not have the original Comb ID stored for this Cell.',
            ], 409);
        }

        $comb = Comb::query()->find($combId);

        if (! $comb) {
            return response()->json([
                'message' => 'The Comb assigned to this Cell no longer exists.',
            ], 409);
        }

        $variables = (array) data_get($cell->metadata ?? [], 'variables', []);

        try {
            $updatedCell = $reinstall->reinstall(
                cell: $cell,
                comb: $comb,
                variables: $variables,
                startAfterInstall: (bool) ($validated['start_on_completion'] ?? false),
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage() ?: 'The Cell recovery reinstall could not be started.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Recovery reinstall has been queued.',
            'cell' => [
                'id' => $updatedCell->id,
                'install_status' => $updatedCell->install_status->value,
                'worker_recovery_required' => $updatedCell->worker_recovery_required,
            ],
        ]);
    }

    private function workerCellIsRunning(array $workerCell): bool
    {
        if (($workerCell['running'] ?? false) === true) {
            return true;
        }

        return strtolower((string) ($workerCell['status'] ?? '')) === 'running';
    }
}