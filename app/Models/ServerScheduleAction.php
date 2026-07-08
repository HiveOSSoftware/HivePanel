<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerScheduleAction extends Model
{
    protected $fillable = [
        'server_schedule_id',
        'sort_order',
        'type',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ServerSchedule::class, 'server_schedule_id');
    }
}