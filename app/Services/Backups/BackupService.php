<?php

namespace App\Services\Backups;

use App\Enums\BackupStatus;
use App\Models\Backup;
use App\Models\Cell;
use App\Models\User;
use App\Services\Node\BackupNodeClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BackupService
{
    public function __construct(
        private readonly BackupNodeClient $backups,
    ) {
    }

    public function create(
        Cell $cell,
        ?User $user,
        ?string $name = null,
        array $ignoredFiles = [],
        bool $automatic = false,
    ): Backup {
        $backup = DB::transaction(function () use (
            $cell,
            $user,
            $name,
            $ignoredFiles,
            $automatic,
        ): Backup {
            return $cell->backups()->create([
                'user_id' => $user?->id,
                'name' => $this->normaliseName($name),
                'status' => BackupStatus::PENDING,
                'archive_name' => null,
                'size' => 0,
                'checksum' => null,
                'checksum_algorithm' => null,
                'is_locked' => false,
                'is_automatic' => $automatic,
                'ignored_files' => $this->normaliseIgnoredFiles(
                    $ignoredFiles,
                ),
                'failure_reason' => null,
                'started_at' => null,
                'completed_at' => null,
            ]);
        });

        try {
            $backup->forceFill([
                'status' => BackupStatus::CREATING,
                'started_at' => now(),
                'completed_at' => null,
                'failure_reason' => null,
            ])->save();

            $result = $this->backups->createBackup(
                cell: $cell,
                backup: $backup,
            );

            $this->validateCreateResult(
                backup: $backup,
                result: $result,
            );

            $backup->forceFill([
                'status' => BackupStatus::COMPLETED,

                'archive_name' => $this->normaliseArchiveName(
                    $result['archive_name']
                        ?? "{$backup->id}.tgz",
                    $backup,
                ),

                'size' => max(
                    0,
                    (int) ($result['size'] ?? 0),
                ),

                'checksum' => isset($result['checksum'])
                    ? trim((string) $result['checksum'])
                    : null,

                'checksum_algorithm' => isset(
                    $result['checksum_algorithm']
                )
                    ? strtolower(trim(
                        (string) $result['checksum_algorithm'],
                    ))
                    : (
                        isset($result['checksum'])
                            ? 'sha256'
                            : null
                    ),

                'completed_at' => $result['completed_at']
                    ?? now(),

                'failure_reason' => null,
            ])->save();

            return $backup->refresh();
        } catch (Throwable $exception) {
            $this->markFailed(
                backup: $backup,
                exception: $exception,
            );

            throw $exception;
        }
    }

    public function restore(Backup $backup): Backup
    {
        $backup->loadMissing('cell');

        if (!$backup->canRestore()) {
            throw new RuntimeException(
                'This backup is not available for restoration.',
            );
        }

        if ($backup->activeMounts()->exists()) {
            throw new RuntimeException(
                'Unmount this backup before restoring it.',
            );
        }

        try {
            $backup->forceFill([
                'status' => BackupStatus::RESTORING,
                'failure_reason' => null,
            ])->save();

            $this->backups->restoreBackup(
                cell: $backup->cell,
                backup: $backup,
            );

            $backup->forceFill([
                'status' => BackupStatus::COMPLETED,
                'failure_reason' => null,
            ])->save();

            return $backup->refresh();
        } catch (Throwable $exception) {
            /*
             * The backup archive itself is still valid even when restoring
             * fails, so return it to COMPLETED rather than marking the
             * archive as FAILED.
             */
            $backup->forceFill([
                'status' => BackupStatus::COMPLETED,
                'failure_reason' => $this->failureMessage(
                    $exception,
                ),
            ])->save();

            throw $exception;
        }
    }

    public function delete(Backup $backup): void
    {
        $backup->loadMissing('cell');

        if ($backup->is_locked) {
            throw new RuntimeException(
                'This backup is locked and cannot be deleted.',
            );
        }

        if ($backup->isProcessing()) {
            throw new RuntimeException(
                'This backup is currently being processed.',
            );
        }

        if ($backup->activeMounts()->exists()) {
            throw new RuntimeException(
                'Unmount this backup before deleting it.',
            );
        }

        $previousStatus = $backup->status;

        try {
            $backup->forceFill([
                'status' => BackupStatus::DELETING,
                'failure_reason' => null,
            ])->save();

            if (
                $backup->archive_name !== null
                || $previousStatus === BackupStatus::COMPLETED
            ) {
                $this->backups->deleteBackup(
                    cell: $backup->cell,
                    backup: $backup,
                );
            }

            $backup->delete();
        } catch (Throwable $exception) {
            $backup->forceFill([
                'status' => $previousStatus,
                'failure_reason' => $this->failureMessage(
                    $exception,
                ),
            ])->save();

            throw $exception;
        }
    }

    public function download(Backup $backup): Response
    {
        $backup->loadMissing('cell');

        if (!$backup->isAvailable()) {
            throw new RuntimeException(
                'This backup is not available for download.',
            );
        }

        return $this->backups->downloadBackup(
            cell: $backup->cell,
            backup: $backup,
        );
    }

    public function lock(Backup $backup): Backup
    {
        if ($backup->isProcessing()) {
            throw new RuntimeException(
                'A backup cannot be locked while it is processing.',
            );
        }

        $backup->forceFill([
            'is_locked' => true,
        ])->save();

        return $backup->refresh();
    }

    public function unlock(Backup $backup): Backup
    {
        $backup->forceFill([
            'is_locked' => false,
        ])->save();

        return $backup->refresh();
    }

    public function failStaleCreatingBackups(
        int $olderThanMinutes = 60,
    ): int {
        return Backup::query()
            ->where(
                'status',
                BackupStatus::CREATING->value,
            )
            ->where(
                'started_at',
                '<',
                now()->subMinutes($olderThanMinutes),
            )
            ->update([
                'status' => BackupStatus::FAILED->value,
                'failure_reason' =>
                    'Backup creation did not complete.',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function validateCreateResult(
        Backup $backup,
        array $result,
    ): void {
        if (
            isset($result['backup_id'])
            && trim((string) $result['backup_id'])
                !== (string) $backup->id
        ) {
            throw new RuntimeException(
                'The Worker returned a different backup ID.',
            );
        }

        if (
            isset($result['size'])
            && (
                !is_numeric($result['size'])
                || (int) $result['size'] < 0
            )
        ) {
            throw new RuntimeException(
                'The Worker returned an invalid backup size.',
            );
        }

        if (
            isset($result['checksum'])
            && !is_string($result['checksum'])
        ) {
            throw new RuntimeException(
                'The Worker returned an invalid checksum.',
            );
        }
    }

    private function normaliseArchiveName(
        string $archiveName,
        Backup $backup,
    ): string {
        $archiveName = trim($archiveName);

        /*
         * Laravel stores only the filename. It must never store or accept a
         * filesystem path supplied by the Worker.
         */
        $archiveName = basename(
            str_replace('\\', '/', $archiveName),
        );

        if ($archiveName === '' || $archiveName === '.') {
            return "{$backup->id}.tgz";
        }

        $expectedNames = [
            "{$backup->id}.tgz",
            "{$backup->id}.tar.gz",
        ];

        if (!in_array($archiveName, $expectedNames, true)) {
            throw new RuntimeException(
                'The Worker returned an unexpected archive name.',
            );
        }

        return $archiveName;
    }

    private function markFailed(
        Backup $backup,
        Throwable $exception,
    ): void {
        $backup->forceFill([
            'status' => BackupStatus::FAILED,
            'failure_reason' => $this->failureMessage(
                $exception,
            ),
            'completed_at' => now(),
        ])->save();
    }

    private function normaliseName(?string $name): string
    {
        $name = trim((string) $name);

        if ($name !== '') {
            return Str::limit(
                $name,
                191,
                '',
            );
        }

        return 'Backup '.now()->format('Y-m-d H:i:s');
    }

    private function normaliseIgnoredFiles(
        array $ignoredFiles,
    ): array {
        return collect($ignoredFiles)
            ->filter(fn ($path) => is_string($path))
            ->map(function (string $path): string {
                $path = str_replace(
                    '\\',
                    '/',
                    trim($path),
                );

                return trim($path, '/');
            })
            ->filter()
            ->reject(function (string $path): bool {
                return $path === '..'
                    || str_starts_with($path, '../')
                    || str_contains($path, '/..')
                    || str_contains($path, "\0");
            })
            ->unique()
            ->values()
            ->all();
    }

    private function failureMessage(
        Throwable $exception,
    ): string {
        return Str::limit(
            $exception->getMessage() !== ''
                ? $exception->getMessage()
                : 'An unknown backup operation error occurred.',
            2000,
            '',
        );
    }
}