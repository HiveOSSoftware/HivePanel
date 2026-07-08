<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comb extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'name',
        'game',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * Get a value from the stored comb definition.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    /**
     * Determine if this comb has an install section.
     */
    public function hasInstaller(): bool
    {
        return ! empty($this->get('install'));
    }

    /**
     * Determine if this comb defines variables.
     */
    public function hasVariables(): bool
    {
        return ! empty($this->get('variables'));
    }

    /**
     * Startup command from the comb definition.
     */
    public function startup(): ?string
    {
        return $this->get('startup');
    }

    /**
     * Docker image from the comb definition.
     */
    public function image(): ?string
    {
        return $this->get('image');
    }
}