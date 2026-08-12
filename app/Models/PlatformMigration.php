<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformMigration extends Model
{
    use HasUuids;

    protected $fillable = [
        'source_type',
        'name',
        'source_config',
        'execution_config',
        'status',
        'current_stage',
        'progress',
        'error',
        'discovered_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'source_config' => 'encrypted:array',
            'execution_config' => 'encrypted:array',
            'progress' => 'integer',
            'discovered_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function servers(): HasMany
    {
        return $this->hasMany(PlatformMigrationServer::class);
    }
}