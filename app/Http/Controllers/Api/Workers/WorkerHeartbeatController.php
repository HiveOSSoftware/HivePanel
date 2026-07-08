<?php

namespace App\Http\Controllers\Api\Workers;

use App\Http\Controllers\Controller;
use App\Models\Node;
use Illuminate\Http\Request;

class WorkerHeartbeatController extends Controller
{
    public function __invoke(Request $request)
    {
        $token = $request->bearerToken();

        abort_unless($token, 401, 'Missing worker token.');

        $node = Node::query()
            ->where('api_token', $token)
            ->firstOrFail();

        $data = $request->validate([
            'version' => ['nullable', 'string', 'max:100'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:255'],
            'stats' => ['nullable', 'array'],
        ]);

        $stats = $data['stats'] ?? [];

        $node->update([
            'last_seen_at' => now(),
            'worker_version' => $data['version'] ?? $node->worker_version,
            'worker_hostname' => $data['hostname'] ?? $node->worker_hostname,
            'worker_platform' => $data['platform'] ?? $node->worker_platform,
            'worker_ip' => $request->ip(),
        ]);

        $node->liveStat()->updateOrCreate(
            ['node_id' => $node->id],
            [
                'host_cpu_used' => data_get($stats, 'host.cpu.used', 0),
                'host_cpu_max' => data_get($stats, 'host.cpu.max', 0),

                'host_memory_used_gb' => data_get($stats, 'host.memory.used_gb', 0),
                'host_memory_max_gb' => data_get($stats, 'host.memory.max_gb', 0),

                'host_disk_used_gb' => data_get($stats, 'host.disk.used_gb', 0),
                'host_disk_max_gb' => data_get($stats, 'host.disk.max_gb', 0),

                'cells_cpu_used' => data_get($stats, 'cells.cpu_used', 0),
                'cells_memory_used_gb' => data_get($stats, 'cells.memory_used_gb', 0),
                'cells_disk_used_gb' => data_get($stats, 'cells.disk_used_gb', 0),

                'cells_total' => data_get($stats, 'cells.total', 0),
                'cells_running' => data_get($stats, 'cells.running', 0),

                'raw' => $stats,
            ]
        );

        return response()->json([
            'ok' => true,
            'node_id' => $node->id,
            'timestamp' => now()->toISOString(),
        ]);
    }
}