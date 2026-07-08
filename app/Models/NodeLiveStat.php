<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NodeLiveStat extends Model
{
    protected $fillable = [
        'node_id',

        'host_cpu_used',
        'host_cpu_max',

        'host_memory_used_gb',
        'host_memory_max_gb',

        'host_disk_used_gb',
        'host_disk_max_gb',

        'cells_cpu_used',
        'cells_memory_used_gb',
        'cells_disk_used_gb',

        'cells_total',
        'cells_running',

        'raw',
    ];

    protected $casts = [
        'host_cpu_used' => 'float',
        'host_cpu_max' => 'float',

        'host_memory_used_gb' => 'float',
        'host_memory_max_gb' => 'float',

        'host_disk_used_gb' => 'float',
        'host_disk_max_gb' => 'float',

        'cells_cpu_used' => 'float',
        'cells_memory_used_gb' => 'float',
        'cells_disk_used_gb' => 'float',

        'cells_total' => 'integer',
        'cells_running' => 'integer',

        'raw' => 'array',
    ];

    public function node()
    {
        return $this->belongsTo(Node::class);
    }
}