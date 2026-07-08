<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServerSchedule extends Model
{
    protected $fillable = [
        'cell_id',
        'name',
        'cron_expression',
        'timezone',
        'enabled',
        'only_when_online',
        'continue_on_failure',
        'last_run_at',
        'next_run_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'only_when_online' => 'boolean',
        'continue_on_failure' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function actions(): HasMany
    {
        return $this->hasMany(ServerScheduleAction::class)->orderBy('sort_order');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ServerScheduleRun::class);
    }
}