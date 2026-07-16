<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SftpCredential extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'cell_id',
        'username',
        'password_hash',
        'last_used_at',
        'revoked_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cell()
    {
        return $this->belongsTo(Cell::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}