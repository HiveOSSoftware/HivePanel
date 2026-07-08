<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'cell_id',
        'event',
        'description',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function cell()
    {
        return $this->belongsTo(Cell::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}