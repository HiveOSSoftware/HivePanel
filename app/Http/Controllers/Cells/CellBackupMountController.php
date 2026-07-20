<?php

namespace App\Http\Controllers\Cells;

use App\Enums\BackupMountStatus;
use App\Enums\BackupStatus;
use App\Models\Backup;
use App\Models\BackupMount;
use App\Services\Node\BackupNodeClient;
use App\Services\Node\CellNodeClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CellBackupMountController extends CellBaseController
{
    public function mount(
        Request $request,
        string $id,
        string $backup,
        CellNodeClient $cells,
        BackupNodeClient $backups,
    ): JsonResponse {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        $this->abortIfLocked($cell, $cells);

        $backupModel = $this->backupOrFail(
            $cell->id,
            $backup,
        );

        if ($backupModel->status !== BackupStatus::COMPLETED) {
            return response()->json([
                'message' => 'Only completed backups can be mounted.',
            ], 409);
        }

        $existingMount = BackupMount::query()
            ->where('backup_id', $backupModel->id)
            ->whereIn('status', [
                BackupMountStatus::MOUNTING,
                BackupMountStatus::MOUNTED,
                BackupMountStatus::UNMOUNTING,
            ])
            ->latest()
            ->first();

        if ($existingMount) {
            return response()->json([
                'message' => 'This backup already has an active mount.',
                'mount' => $this->mountData($existingMount),
            ], 409);
        }

        $mount = BackupMount::query()->create([
            'cell_id' => $cell->id,
            'backup_id' => $backupModel->id,
            'user_id' => $request->user()->id,
            'status' => BackupMountStatus::MOUNTING,
            'expires_at' => now()->addHour(),
        ]);

        try {
            $result = $backups->mountBackup(
                $cell,
                $backupModel,
                $mount,
            );

            $mount->markMounted(
                workerMountPath: (string) (
                    $result['mount_path']
                    ?? $result['path']
                    ?? ''
                ),
                extractedSize: (int) (
                    $result['extracted_size']
                    ?? $result['size']
                    ?? 0
                ),
            );

            return response()->json([
                'message' => 'Backup mounted successfully.',
                'mount' => $this->mountData($mount->fresh()),
            ]);
        } catch (Throwable $exception) {
            Log::error('Unable to mount backup', [
                'cell_id' => $cell->id,
                'backup_id' => $backupModel->id,
                'mount_id' => $mount->id,
                'exception' => $exception,
            ]);

            $mount->markFailed($exception->getMessage());

            return response()->json([
                'message' => 'The backup could not be mounted.',
            ], 502);
        }
    }

    public function unmount(
        string $id,
        string $backup,
        string $mount,
        CellNodeClient $cells,
        BackupNodeClient $backups,
    ): JsonResponse {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        $this->abortIfLocked($cell, $cells);

        $backupModel = $this->backupOrFail(
            $cell->id,
            $backup,
        );

        $mountModel = $this->mountOrFail(
            $cell->id,
            $backupModel->id,
            $mount,
        );

        if ($mountModel->status === BackupMountStatus::UNMOUNTED) {
            return response()->json([
                'message' => 'This backup is already unmounted.',
                'mount' => $this->mountData($mountModel),
            ]);
        }

        if ($mountModel->status === BackupMountStatus::UNMOUNTING) {
            return response()->json([
                'message' => 'This backup is already being unmounted.',
                'mount' => $this->mountData($mountModel),
            ], 409);
        }

        if (! in_array($mountModel->status, [
            BackupMountStatus::MOUNTED,
            BackupMountStatus::FAILED,
        ], true)) {
            return response()->json([
                'message' => 'This backup mount cannot currently be unmounted.',
            ], 409);
        }

        $mountModel->markUnmounting();

        try {
            $backups->unmountBackup(
                $cell,
                $mountModel,
            );

            $mountModel->markUnmounted();

            return response()->json([
                'message' => 'Backup unmounted successfully.',
                'mount' => $this->mountData($mountModel->fresh()),
            ]);
        } catch (Throwable $exception) {
            Log::error('Unable to unmount backup', [
                'cell_id' => $cell->id,
                'backup_id' => $backupModel->id,
                'mount_id' => $mountModel->id,
                'exception' => $exception,
            ]);

            $mountModel->markFailed($exception->getMessage());

            return response()->json([
                'message' => 'The backup could not be unmounted.',
            ], 502);
        }
    }

    public function files(
        Request $request,
        string $id,
        string $backup,
        string $mount,
        BackupNodeClient $backups,
    ): JsonResponse {
        $cell = $this->panelCellOrFail($id);
        if ($response = $this->installationPageIfNeeded($cell)) {
            return $response;
        }

        $backupModel = $this->backupOrFail(
            $cell->id,
            $backup,
        );

        $mountModel = $this->mountOrFail(
            $cell->id,
            $backupModel->id,
            $mount,
        );

        if (! $mountModel->isBrowsable()) {
            return response()->json([
                'message' => $mountModel->hasExpired()
                    ? 'This backup mount has expired.'
                    : 'This backup is not currently mounted.',
            ], 409);
        }

        $validated = $request->validate([
            'path' => [
                'nullable',
                'string',
                'max:4096',
            ],
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:500',
            ],
        ]);

        try {
            $result = $backups->mountedBackupFiles(
                cell: $cell,
                mount: $mountModel,
                path: (string) ($validated['path'] ?? ''),
                page: (int) ($validated['page'] ?? 1),
                perPage: (int) ($validated['per_page'] ?? 250),
            );

            return response()->json($result);
        } catch (Throwable $exception) {
            Log::error('Unable to list mounted backup files', [
                'cell_id' => $cell->id,
                'backup_id' => $backupModel->id,
                'mount_id' => $mountModel->id,
                'path' => $validated['path'] ?? '',
                'exception' => $exception,
            ]);

            return response()->json([
                'message' => 'The mounted backup files could not be loaded.',
            ], 502);
        }
    }

    private function backupOrFail(
        string $cellID,
        string $backupID,
    ): Backup {
        return Backup::query()
            ->where('cell_id', $cellID)
            ->whereKey($backupID)
            ->firstOrFail();
    }

    private function mountOrFail(
        string $cellID,
        string $backupID,
        string $mountID,
    ): BackupMount {
        return BackupMount::query()
            ->where('cell_id', $cellID)
            ->where('backup_id', $backupID)
            ->whereKey($mountID)
            ->firstOrFail();
    }

    private function mountData(BackupMount $mount): array
    {
        return [
            'id' => $mount->id,
            'backup_id' => $mount->backup_id,
            'status' => $mount->status->value,
            'status_label' => $mount->status->label(),
            'extracted_size' => $mount->extracted_size,
            'failure_reason' => $mount->failure_reason,
            'mounted_at' => $mount->mounted_at?->toIso8601String(),
            'expires_at' => $mount->expires_at?->toIso8601String(),
            'unmounted_at' => $mount->unmounted_at?->toIso8601String(),
            'is_active' => $mount->isActive(),
            'is_browsable' => $mount->isBrowsable(),
            'has_expired' => $mount->hasExpired(),
        ];
    }
}