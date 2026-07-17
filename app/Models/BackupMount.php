<?php

namespace App\Models;

use App\Enums\BackupMountStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupMount extends Model
{
    use HasUuids;

    protected $fillable = [
        'cell_id',
        'backup_id',
        'user_id',
        'status',
        'worker_mount_path',
        'failure_reason',
        'extracted_size',
        'mounted_at',
        'expires_at',
        'unmounted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BackupMountStatus::class,
            'extracted_size' => 'integer',
            'mounted_at' => 'datetime',
            'expires_at' => 'datetime',
            'unmounted_at' => 'datetime',
        ];
    }

    public function cell(): BelongsTo
    {
        return $this->belongsTo(Cell::class);
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(Backup::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isBrowsable(): bool
    {
        return $this->status->isBrowsable()
            && $this->mounted_at !== null
            && $this->unmounted_at === null
            && (
                $this->expires_at === null
                || $this->expires_at->isFuture()
            );
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function markMounted(
        string $workerMountPath,
        int $extractedSize = 0,
    ): void {
        $this->forceFill([
            'status' => BackupMountStatus::MOUNTED,
            'worker_mount_path' => $workerMountPath,
            'extracted_size' => $extractedSize,
            'failure_reason' => null,
            'mounted_at' => now(),
            'unmounted_at' => null,
        ])->save();
    }

    public function markUnmounting(): void
    {
        $this->forceFill([
            'status' => BackupMountStatus::UNMOUNTING,
        ])->save();
    }

    public function markUnmounted(): void
    {
        $this->forceFill([
            'status' => BackupMountStatus::UNMOUNTED,
            'worker_mount_path' => null,
            'unmounted_at' => now(),
        ])->save();
    }

    public function markFailed(string $reason): void
    {
        $this->forceFill([
            'status' => BackupMountStatus::FAILED,
            'failure_reason' => $reason,
            'worker_mount_path' => null,
        ])->save();
    }
}