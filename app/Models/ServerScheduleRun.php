<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerScheduleRun extends Model
{
    protected $fillable = [
        'server_schedule_id',
        'status',
        'output',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ServerSchedule::class, 'server_schedule_id');
    }

    public function actionLogs()
    {
        return $this->hasMany(ServerScheduleRunAction::class)
            ->orderBy('sort_order');
    }
}