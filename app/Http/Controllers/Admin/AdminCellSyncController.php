<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cell;
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

    public function repair(string $id, CellSyncService $sync): JsonResponse
    {
        $cell = Cell::query()->with(['node', 'allocation'])->findOrFail($id);

        try {
            $result = $sync->repair($cell);

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
}