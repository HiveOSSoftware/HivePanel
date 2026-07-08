<?php

namespace App\Http\Controllers\Api\Workers;

use App\Http\Controllers\Controller;
use App\Models\Node;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkerRegistrationController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'registration_token' => ['required', 'string'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:100'],
        ]);

        $hashedToken = hash('sha256', $data['registration_token']);

        $node = Node::query()
            ->where('registration_token', $hashedToken)
            ->where(function ($query) {
                $query
                    ->whereNull('registration_token_expires_at')
                    ->orWhere('registration_token_expires_at', '>', now());
            })
            ->firstOrFail();

        $workerToken = 'hpwk_' . Str::random(64);

        $node->update([
            'api_token' => $workerToken,
            'registration_token' => null,
            'registration_token_expires_at' => null,
            'is_registered' => true,
            'registered_at' => now(),
            'last_seen_at' => now(),
            'worker_hostname' => $data['hostname'] ?? null,
            'worker_platform' => $data['platform'] ?? null,
            'worker_version' => $data['version'] ?? null,
            'worker_ip' => $request->ip(),
        ]);

        return response()->json([
            'node_id' => $node->id,
            'token' => $workerToken,
            'panel_url' => rtrim(config('app.url'), '/'),
            'server' => [
                'host' => '0.0.0.0',
                'port' => $node->daemon_port,
                'sftp_port' => $node->sftp_port,
                'behind_proxy' => $node->behind_proxy,
            ],
            'paths' => [
                'data' => '/var/lib/hivepanel/cells',
                'backups' => '/var/lib/hivepanel/backups',
            ],
        ]);
    }
}