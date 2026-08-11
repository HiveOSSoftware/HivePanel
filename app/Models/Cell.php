<?php

namespace App\Models;

use App\Enums\CellInstallStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cell extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'uuid',
        'owner_id',
        'node_id',
        'daemon_id',
        'name',
        'comb',
        'metadata',
        'install_status',
        'install_failure_reason',
        'installed_at',
        'worker_sync_status',
        'worker_sync_message',
        'worker_sync_differences',
        'worker_sync_checked_at',
    ];

    protected $appends = [
        'variables',
        'comb_data',
        'limits',
        'feature_limits',
        'docker',
        'startup',
        'additional_allocations',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'install_status' => CellInstallStatus::class,
            'installed_at' => 'datetime',
            'worker_sync_differences' => 'array',
            'worker_sync_checked_at' => 'datetime',
        ];
    }

    public function getVariablesAttribute(): array
    {
        return data_get($this->metadata ?? [], 'variables', []);
    }

    public function getCombDataAttribute(): array
    {
        return data_get($this->metadata ?? [], 'comb_data', []);
    }

    public function getLimitsAttribute(): array
    {
        return data_get($this->metadata ?? [], 'limits', []);
    }

    public function getFeatureLimitsAttribute(): array
    {
        return data_get($this->metadata ?? [], 'feature_limits', []);
    }

    public function getDockerAttribute(): array
    {
        return data_get($this->metadata ?? [], 'docker', []);
    }

    public function getStartupAttribute(): array
    {
        return data_get($this->metadata ?? [], 'startup', []);
    }

    public function getAdditionalAllocationsAttribute(): array
    {
        return data_get($this->metadata ?? [], 'additional_allocations', []);
    }

    public function scopeVisibleTo($query, User $user)
    {
        return $query->where(function ($query) use ($user) {
            $query->where('owner_id', $user->id)
                ->orWhereHas('subUsers', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                });
        });
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function subUsers()
    {
        return $this->hasMany(CellUser::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'cell_users')
            ->withPivot(['permissions', 'accepted_at'])
            ->withTimestamps();
    }

    public function userCan(User $user, string $permission): bool
    {
        if ($this->isOwner($user)) {
            return true;
        }

        $subUser = $this->subUsers()
            ->where('user_id', $user->id)
            ->first();

        return $subUser?->hasPermission($permission) ?? false;
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isOwner(User $user): bool
    {
        return (string) $this->owner_id === (string) $user->id;
    }

    public function node()
    {
        return $this->belongsTo(Node::class, 'node_id');
    }

    public function allocation()
    {
        return $this->hasOne(NodeAllocation::class);
    }

    public function allocations()
    {
        return $this->hasMany(NodeAllocation::class);
    }

    public function sftpCredentials()
    {
        return $this->hasMany(\App\Models\SftpCredential::class);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class);
    }

    public function backupMounts(): HasMany
    {
        return $this->hasMany(BackupMount::class);
    }
}