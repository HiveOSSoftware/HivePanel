<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OAuthProvider extends Model
{
    use HasUuids;
    protected $table = 'oauth_providers';
    protected $keyType = 'string';

    public $incrementing = false;
    
    protected $fillable = [
        'provider',
        'enabled',
        'client_id',
        'client_secret',
        'redirect_url',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'client_secret' => 'encrypted',
    ];
}