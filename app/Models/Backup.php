<?php

namespace App\Models;

use App\Enums\BackupStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Backup extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'cell_id',
        'user_id',
        'name',
        'status',
        'archive_name',
        'size',
        'checksum',
        'checksum_algorithm',
        'is_locked',
        'is_automatic',
        'ignored_files',
        'failure_reason',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BackupStatus::class,
            'size' => 'integer',
            'is_locked' => 'boolean',
            'is_automatic' => 'boolean',
            'ignored_files' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function cell(): BelongsTo
    {
        return $this->belongsTo(Cell::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mounts(): HasMany
    {
        return $this->hasMany(BackupMount::class);
    }

    public function activeMounts(): HasMany
    {
        return $this->mounts()
            ->whereIn('status', [
                'mounting',
                'mounted',
                'unmounting',
            ]);
    }

    public function isAvailable(): bool
    {
        return $this->status->isAvailable()
            && !$this->trashed();
    }

    public function isProcessing(): bool
    {
        return $this->status->isProcessing();
    }

    public function canDelete(): bool
    {
        return !$this->is_locked
            && !$this->isProcessing()
            && !$this->trashed();
    }

    public function canRestore(): bool
    {
        return $this->isAvailable();
    }
}