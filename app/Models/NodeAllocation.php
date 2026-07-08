<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NodeAllocation extends Model
{
    protected $fillable = [
        'node_id',
        'cell_id',
        'ip',
        'port',
        'alias',
        'notes',
        'is_reserved',
    ];

    protected $casts = [
        'is_reserved' => 'boolean',
    ];

    public function node()
    {
        return $this->belongsTo(Node::class);
    }

    public function cell()
    {
        return $this->belongsTo(Cell::class);
    }

    public function isAssigned(): bool
    {
        return filled($this->cell_id);
    }

    public function isAvailable(): bool
    {
        return ! $this->is_reserved && blank($this->cell_id);
    }
}