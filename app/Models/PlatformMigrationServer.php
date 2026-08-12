<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PlatformMigrationServer extends Model
{
    use HasUuids;

    protected $fillable = [
        'platform_migration_id',
        'source_server_id',
        'source_uuid',
        'name',
        'owner_email',
        'source_node_name',
        'source_egg_name',
        'source_metadata',
        'source_allocations',
        'selected',
        'destination_node_id',
        'destination_owner_id',
        'owner_strategy',
        'owner_create_data',
        'transfer_owner',
        'destination_cell_id',
        'destination_comb',
        'comb_strategy',
        'comb_create_data',
        'allocation_strategy',
        'execution_plan',
        'database_plan',
        'status',
        'current_stage',
        'progress',
        'error',
        'started_at',
        'completed_at',
        'prepared_at',
    ];

    protected function casts(): array
    {
        return [
            'source_metadata' => 'array',
            'source_allocations' => 'array',
            'selected' => 'boolean',
            'owner_create_data' => 'array',
            'transfer_owner' => 'boolean',
            'comb_create_data' => 'array',
            'execution_plan' => 'array',
            'database_plan' => 'array',
            'progress' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'prepared_at' => 'datetime',
        ];
    }

    public function migration()
    {
        return $this->belongsTo(PlatformMigration::class, 'platform_migration_id');
    }

    public function destinationNode()
    {
        return $this->belongsTo(Node::class, 'destination_node_id');
    }

    public function destinationOwner()
    {
        return $this->belongsTo(User::class, 'destination_owner_id');
    }

    public function destinationCell()
    {
        return $this->belongsTo(Cell::class, 'destination_cell_id');
    }
}