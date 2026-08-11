<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\Cell;
use App\Services\AuditLogger;
use App\Services\Cells\CellSyncService;
use Illuminate\Http\JsonResponse;
use Throwable;

class AdminCellSyncController extends Controller
{
    public function show(string $id, CellSyncService $sync): JsonResponse
    {
        $cell = Cell::query()->with(['node', 'allocation'])->findOrFail($id);

        try {
            return response()->json($sync->inspect($cell));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => 'error',
                'synced' => false,
                'repairable' => false,
                'message' => $exception->getMessage() ?: 'Unable to inspect the Worker cell.',
                'differences' => [],
            ], 500);
        }
    }

    public function repair(string $id, CellSyncService $sync, AuditLogger $audit): JsonResponse
    {
        $cell = Cell::query()->with(['node', 'allocation'])->findOrFail($id);

        try {
            $before = $sync->inspect($cell);

            if ($before['synced']) {
                return response()->json([
                    ...$before,
                    'message' => 'Cell definition is already in sync.',
                ]);
            }

            $result = $sync->repair($cell);

            $audit->log(
                AuditEvent::SERVER_SYNC_REPAIRED,
                $cell,
                "Worker definition for server \"{$cell->name}\" was repaired.",
                [
                    'node_id' => $cell->node_id,
                    'daemon_id' => $cell->daemon_id,
                    'repaired_fields' => collect($before['differences'] ?? [])
                        ->pluck('field')
                        ->values()
                        ->all(),
                    'differences' => $before['differences'] ?? [],
                ]
            );

            return response()->json([
                ...$result,
                'message' => 'Cell definition repaired successfully.',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => 'error',
                'synced' => false,
                'repairable' => false,
                'message' => $exception->getMessage() ?: 'Unable to repair the Worker cell.',
                'differences' => [],
            ], 409);
        }
    }

    public function recreate(string $id, CellSyncService $sync, AuditLogger $audit): JsonResponse
    {
        $cell = Cell::query()->with(['node', 'allocation'])->findOrFail($id);

        try {
            $before = $sync->inspect($cell);

            if ($before['status'] !== 'missing') {
                return response()->json([
                    ...$before,
                    'message' => 'This Cell is not missing from the Worker.',
                ], 409);
            }

            $result = $sync->recreateMissing($cell);

            $audit->log(
                AuditEvent::SERVER_SYNC_RECREATED,
                $cell,
                "Worker Cell for server \"{$cell->name}\" was recreated.",
                [
                    'node_id' => $cell->node_id,
                    'daemon_id' => $cell->daemon_id,
                    'allocation' => $cell->allocation
                        ? "{$cell->allocation->ip}:{$cell->allocation->port}"
                        : null,
                ]
            );

            return response()->json([
                ...$result,
                'message' => 'Worker Cell recreated successfully.',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => 'error',
                'synced' => false,
                'repairable' => false,
                'message' => $exception->getMessage() ?: 'Unable to recreate the Worker Cell.',
                'differences' => [],
            ], 409);
        }
    }
}