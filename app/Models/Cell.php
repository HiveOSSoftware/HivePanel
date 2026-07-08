<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

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
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

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
}