<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerScheduleRunAction extends Model
{
    protected $fillable = [
        'server_schedule_run_id',
        'server_schedule_action_id',
        'sort_order',
        'type',
        'status',
        'payload',
        'result',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function run()
    {
        return $this->belongsTo(ServerScheduleRun::class, 'server_schedule_run_id');
    }

    public function action()
    {
        return $this->belongsTo(ServerScheduleAction::class, 'server_schedule_action_id');
    }
}