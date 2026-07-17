<?php

namespace App\Http\Controllers\Cells;

use App\Enums\AuditEvent;
use App\Models\Backup;
use App\Services\AuditLogger;
use App\Services\Backups\BackupService;
use App\Services\Node\CellNodeClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CellBackupController extends CellBaseController
{
    public function index(
        string $id,
        CellNodeClient $cells
    ): Response {
        $cell = $this->panelCellOrFail($id);
        $workerCell = $this->getCellOrFail($cell, $cells);

        $backups = $cell->backups()
            ->with([
                'user:id,name',
                'mounts' => fn ($query) => $query
                    ->whereIn('status', [
                        'mounting',
                        'mounted',
                        'unmounting',
                    ]),
            ])
            ->latest()
            ->paginate(20)
            ->through(fn (Backup $backup) => [
                'id' => $backup->id,
                'name' => $backup->name,
                'status' => $backup->status->value,
                'status_label' => $backup->status->label(),
                'archive_name' => $backup->archive_name,
                'size' => $backup->size,
                'checksum' => $backup->checksum,
                'checksum_algorithm' => $backup->checksum_algorithm,
                'is_locked' => $backup->is_locked,
                'is_automatic' => $backup->is_automatic,
                'failure_reason' => $backup->failure_reason,
                'started_at' => $backup->started_at?->toIso8601String(),
                'completed_at' => $backup->completed_at?->toIso8601String(),
                'created_at' => $backup->created_at?->toIso8601String(),

                'user' => $backup->user
                    ? [
                        'id' => $backup->user->id,
                        'name' => $backup->user->name,
                    ]
                    : null,

                'can_download' => $backup->isAvailable(),
                'can_restore' => $backup->canRestore(),
                'can_delete' => $backup->canDelete(),

                'active_mount' => $backup->mounts
                    ->first()
                    ?->only([
                        'id',
                        'status',
                        'mounted_at',
                        'expires_at',
                    ]),
            ]);

        return Inertia::render('Cells/Backups', [
            'cell' => $workerCell,
            'backups' => $backups,
        ]);
    }

    public function json(string $id): JsonResponse
    {
        $cell = $this->panelCellOrFail($id);

        $backups = $cell->backups()
            ->with('user:id,name')
            ->latest()
            ->paginate(20);

        return response()->json($backups);
    }

    public function create(
        Request $request,
        string $id,
        CellNodeClient $cells,
        BackupService $backupService,
        AuditLogger $audit
    ): JsonResponse {
        $cell = $this->panelCellOrFail($id);

        $this->abortIfLocked($cell, $cells);

        $validated = $request->validate([
            'name' => [
                'nullable',
                'string',
                'max:191',
            ],

            'ignored_files' => [
                'sometimes',
                'array',
            ],

            'ignored_files.*' => [
                'string',
                'max:1024',
            ],
        ]);

        $backup = $backupService->create(
            cell: $cell,
            user: $request->user(),
            name: $validated['name'] ?? null,
            ignoredFiles: $validated['ignored_files'] ?? [],
        );

        $audit->log(
            AuditEvent::BACKUP_CREATED,
            $cell,
            "Backup \"{$backup->name}\" was created.",
            [
                'backup_id' => $backup->id,
                'backup_name' => $backup->name,
                'archive_name' => $backup->archive_name,
                'size' => $backup->size,
            ],
        );

        return response()->json([
            'backup' => $backup->fresh([
                'user:id,name',
            ]),
        ], 201);
    }

    public function delete(
        string $id,
        string $backup,
        CellNodeClient $cells,
        BackupService $backupService,
        AuditLogger $audit
    ): JsonResponse {
        $cell = $this->panelCellOrFail($id);

        $this->abortIfLocked($cell, $cells);

        $backupModel = $this->backupOrFail(
            $cell->id,
            $backup,
        );

        $backupName = $backupModel->name;
        $backupID = $backupModel->id;

        $backupService->delete($backupModel);

        $audit->log(
            AuditEvent::BACKUP_DELETED,
            $cell,
            "Backup \"{$backupName}\" was deleted.",
            [
                'backup_id' => $backupID,
                'backup_name' => $backupName,
            ],
        );

        return response()->json([
            'success' => true,
        ]);
    }

    public function restore(
        string $id,
        string $backup,
        CellNodeClient $cells,
        BackupService $backupService,
        AuditLogger $audit
    ): JsonResponse {
        $cell = $this->panelCellOrFail($id);

        $this->abortIfLocked($cell, $cells);

        $backupModel = $this->backupOrFail(
            $cell->id,
            $backup,
        );

        $backupService->restore($backupModel);

        $audit->log(
            AuditEvent::BACKUP_RESTORED,
            $cell,
            "Backup \"{$backupModel->name}\" was restored.",
            [
                'backup_id' => $backupModel->id,
                'backup_name' => $backupModel->name,
            ],
        );

        return response()->json([
            'success' => true,
            'backup' => $backupModel->fresh(),
        ]);
    }

    public function download(
        string $id,
        string $backup,
        BackupService $backupService,
        AuditLogger $audit
    ): StreamedResponse {
        $cell = $this->panelCellOrFail($id);

        $backupModel = $this->backupOrFail(
            $cell->id,
            $backup,
        );

        $workerResponse = $backupService->download(
            $backupModel,
        );

        $audit->log(
            AuditEvent::BACKUP_DOWNLOADED,
            $cell,
            "Backup \"{$backupModel->name}\" was downloaded.",
            [
                'backup_id' => $backupModel->id,
                'backup_name' => $backupModel->name,
                'archive_name' => $backupModel->archive_name,
            ],
        );

        $filename = basename(
            $backupModel->archive_name
                ?: "{$backupModel->id}.tgz",
        );

        $contentDisposition = $workerResponse->header(
            'Content-Disposition',
        );

        if (!$contentDisposition) {
            $safeFilename = str_replace(
                ['\\', '"', "\r", "\n"],
                '',
                $filename,
            );

            $contentDisposition =
                "attachment; filename=\"{$safeFilename}\"";
        }

        $headers = [
            'Content-Type' => $workerResponse->header(
                'Content-Type',
                'application/gzip',
            ),

            'Content-Disposition' => $contentDisposition,

            'X-Content-Type-Options' => 'nosniff',
        ];

        $contentLength = $workerResponse->header(
            'Content-Length',
        );

        if ($contentLength !== null && $contentLength !== '') {
            $headers['Content-Length'] = $contentLength;
        }

        $stream = $workerResponse
            ->toPsrResponse()
            ->getBody();

        return response()->stream(
            static function () use ($stream): void {
                while (!$stream->eof()) {
                    echo $stream->read(1024 * 1024);

                    if (function_exists('ob_flush')) {
                        @ob_flush();
                    }

                    flush();
                }
            },
            $workerResponse->status(),
            $headers,
        );
    }

    public function lock(
        string $id,
        string $backup,
        BackupService $backupService,
        AuditLogger $audit
    ): JsonResponse {
        $cell = $this->panelCellOrFail($id);

        $backupModel = $this->backupOrFail(
            $cell->id,
            $backup,
        );

        $backupModel = $backupService->lock(
            $backupModel,
        );

        $audit->log(
            AuditEvent::BACKUP_LOCKED,
            $cell,
            "Backup \"{$backupModel->name}\" was locked.",
            [
                'backup_id' => $backupModel->id,
                'backup_name' => $backupModel->name,
            ],
        );

        return response()->json([
            'backup' => $backupModel->fresh(),
        ]);
    }

    public function unlock(
        string $id,
        string $backup,
        BackupService $backupService,
        AuditLogger $audit
    ): JsonResponse {
        $cell = $this->panelCellOrFail($id);

        $backupModel = $this->backupOrFail(
            $cell->id,
            $backup,
        );

        $backupModel = $backupService->unlock(
            $backupModel,
        );

        $audit->log(
            AuditEvent::BACKUP_UNLOCKED,
            $cell,
            "Backup \"{$backupModel->name}\" was unlocked.",
            [
                'backup_id' => $backupModel->id,
                'backup_name' => $backupModel->name,
            ],
        );

        return response()->json([
            'backup' => $backupModel->fresh(),
        ]);
    }

    private function backupOrFail(
        string $cellID,
        string $backupID
    ): Backup {
        return Backup::query()
            ->where('cell_id', $cellID)
            ->whereKey($backupID)
            ->firstOrFail();
    }
}