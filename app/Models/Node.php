<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Node extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'description',
        'location',

        'public_fqdn',
        'internal_fqdn',
        'fqdn',

        'scheme',
        'port',
        'daemon_port',
        'sftp_port',
        'sftp_enabled',
        'sftp_fqdn',

        'api_token',

        'behind_proxy',
        'maintenance_mode',
        'is_active',

        'cpu_threads',
        'memory_mib',
        'memory_overallocate',
        'disk_mib',
        'disk_overallocate',
        'max_upload_mib',

        'metadata',

        'registration_token',
        'registration_token_expires_at',
        'is_registered',
        'registered_at',
        'last_seen_at',
        'worker_version',
        'worker_hostname',
        'worker_platform',
        'worker_ip',
    ];

    protected $casts = [
        'port' => 'integer',
        'daemon_port' => 'integer',
        'sftp_port' => 'integer',
        'sftp_enabled' => 'boolean',

        'behind_proxy' => 'boolean',
        'maintenance_mode' => 'boolean',
        'is_active' => 'boolean',

        'cpu_threads' => 'float',
        'memory_mib' => 'integer',
        'memory_overallocate' => 'integer',
        'disk_mib' => 'integer',
        'disk_overallocate' => 'integer',
        'max_upload_mib' => 'integer',

        'metadata' => 'array',

        'registration_token_expires_at' => 'datetime',
        'is_registered' => 'boolean',
        'registered_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function cells()
    {
        return $this->hasMany(Cell::class);
    }

    public function baseUrl(): string
    {
        $host = $this->internal_fqdn ?: $this->public_fqdn ?: $this->fqdn;
        $port = $this->daemon_port ?: $this->port;

        return "{$this->scheme}://{$host}:{$port}";
    }

    public function publicUrl(): string
    {
        $host = $this->public_fqdn ?: $this->fqdn;
        $port = $this->daemon_port ?: $this->port;

        return "{$this->scheme}://{$host}:{$port}";
    }

    public function usableMemoryMiB(): int
    {
        $base = (int) ($this->memory_mib ?? 0);

        return (int) floor($base + ($base * ((int) $this->memory_overallocate / 100)));
    }

    public function usableDiskMiB(): int
    {
        $base = (int) ($this->disk_mib ?? 0);

        return (int) floor($base + ($base * ((int) $this->disk_overallocate / 100)));
    }

    public function liveStat()
    {
        return $this->hasOne(NodeLiveStat::class);
    }

    public function allocations()
    {
        return $this->hasMany(NodeAllocation::class);
    }

    public function sftpHost(): string
    {
        return $this->sftp_fqdn ?: $this->fqdn;
    }

    public function sftpAddress(): string
    {
        return $this->sftpHost() . ':' . $this->sftp_port;
    }
}