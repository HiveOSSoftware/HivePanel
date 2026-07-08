<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CellUser extends Model
{
    protected $fillable = [
        'cell_id',
        'user_id',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    public function cell()
    {
        return $this->belongsTo(Cell::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }
}