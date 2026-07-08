<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminNodeController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Nodes/Index', [
            'nodes' => Node::query()
                ->with('liveStat')
                ->latest()
                ->get()
                ->map(fn (Node $node) => $this->nodePayload($node)),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Nodes/Create');
    }

    public function show(Node $node)
    {
        $node->load('liveStat');

        return Inertia::render('Admin/Nodes/Show', [
            'node' => $this->nodePayload($node),
            'cells' => $node->cells()
                ->with('owner:id,name,email')
                ->latest()
                ->get()
                ->map(fn ($cell) => [
                    'id' => $cell->id,
                    'name' => $cell->name,
                    'comb' => $cell->comb,
                    'daemon_id' => $cell->daemon_id,
                    'owner' => $cell->owner ? [
                        'name' => $cell->owner->name,
                        'email' => $cell->owner->email,
                    ] : null,
                    'created_at' => $cell->created_at?->toISOString(),
                ]),
        ]);
    }

    public function settings(Node $node)
    {
        return Inertia::render('Admin/Nodes/Settings', [
            'node' => $this->nodePayload($node),
        ]);
    }

    public function configuration(Node $node)
    {
        $panelUrl = rtrim(config('app.url'), '/');
        $registrationToken = session('registration_token');

        $workerYaml = <<<YAML
panel:
  url: "{$panelUrl}"

worker:
  registration_token: "{$registrationToken}"
  token: ""
  listen: "0.0.0.0:{$node->daemon_port}"

node:
  id: ""

paths:
  data: "/var/lib/hivepanel"
  instances: "/var/lib/hivepanel/cells"
  backups: "/var/lib/hivepanel/backups"

runtime:
  type: "docker"

docker:
  network: "hivepanel"

allocations:
  ip: "0.0.0.0"
  port_start: 25565
  port_end: 25600
YAML;

        $systemdService = <<<SERVICE
[Unit]
Description=HivePanel Worker
After=network.target

[Service]
User=root
WorkingDirectory=/var/lib/hivepanel
ExecStart=/usr/local/bin/hivepanel-worker --config /etc/hivepanel/worker.yml
Restart=always
RestartSec=5
LimitNOFILE=1048576

[Install]
WantedBy=multi-user.target
SERVICE;

        return Inertia::render('Admin/Nodes/Configuration', [
            'node' => $this->nodePayload($node),
            'registrationToken' => $registrationToken,
            'workerYaml' => $workerYaml,
            'systemdService' => $systemdService,
            'commands' => [
                'sudo mkdir -p /etc/hivepanel /var/lib/hivepanel/cells /var/lib/hivepanel/backups',
                'sudo nano /etc/hivepanel/worker.yml',
                'sudo nano /etc/systemd/system/hivepanel-worker.service',
                'sudo systemctl daemon-reload',
                'sudo systemctl enable --now hivepanel-worker',
                'sudo systemctl status hivepanel-worker',
            ],
        ]);
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $data = $this->validated($request, true);

        $node = Node::create([
            ...$data,
            'api_token' => null,
            'fqdn' => $data['public_fqdn'],
            'port' => $data['daemon_port'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        $audit->log(AuditEvent::NODE_CREATED, null, "Node \"{$node->name}\" was created.", [
            'node_id' => $node->id,
        ]);

        return redirect()->route('admin.nodes.configuration', $node);
    }

    public function update(Node $node, Request $request, AuditLogger $audit)
    {
        return $this->updateSettings($node, $request, $audit);
    }

    public function updateSettings(Node $node, Request $request, AuditLogger $audit)
    {
        $data = $this->validated($request, false);

        if (blank($data['api_token'] ?? null)) {
            unset($data['api_token']);
        }

        $node->update([
            ...$data,
            'fqdn' => $data['public_fqdn'],
            'port' => $data['daemon_port'],
            'is_active' => $data['is_active'] ?? false,
        ]);

        $audit->log(AuditEvent::NODE_UPDATED, null, "Node \"{$node->name}\" settings were updated.", [
            'node_id' => $node->id,
        ]);

        return redirect()->route('admin.nodes.show', $node);
    }

    public function destroy(Node $node, AuditLogger $audit)
    {
        abort_if($node->cells()->exists(), 422, 'Cannot delete a node that has cells.');

        $name = $node->name;
        $id = $node->id;

        $node->delete();

        $audit->log(AuditEvent::NODE_DELETED, null, "Node \"{$name}\" was deleted.", [
            'node_id' => $id,
        ]);

        return back();
    }

    public function statsJson(Node $node)
    {
        $node->load('liveStat');

        return response()->json([
            'host' => [
                'cpu' => [
                    'used' => $node->liveStat?->host_cpu_used ?? 0,
                    'max' => $node->liveStat?->host_cpu_max ?? 0,
                ],
                'memory' => [
                    'used_gb' => $node->liveStat?->host_memory_used_gb ?? 0,
                    'max_gb' => $node->liveStat?->host_memory_max_gb ?? 0,
                ],
                'disk' => [
                    'used_gb' => $node->liveStat?->host_disk_used_gb ?? 0,
                    'max_gb' => $node->liveStat?->host_disk_max_gb ?? 0,
                ],
            ],
            'cells' => [
                'cpu_used' => $node->liveStat?->cells_cpu_used ?? 0,
                'memory_used_gb' => $node->liveStat?->cells_memory_used_gb ?? 0,
                'disk_used_gb' => $node->liveStat?->cells_disk_used_gb ?? 0,
                'total' => $node->liveStat?->cells_total ?? 0,
                'running' => $node->liveStat?->cells_running ?? 0,
            ],
            'limits' => [
                'cpu' => $node->cpu_threads ?: ($node->liveStat?->host_cpu_max ?? 0),
                'memory_gb' => $node->memory_mib
                    ? round($node->usableMemoryMiB() / 1024, 2)
                    : ($node->liveStat?->host_memory_max_gb ?? 0),
                'disk_gb' => $node->disk_mib
                    ? round($node->usableDiskMiB() / 1024, 2)
                    : ($node->liveStat?->host_disk_max_gb ?? 0),
            ],
            'updated_at' => $node->liveStat?->updated_at?->toISOString(),
        ]);
    }

    public function generateRegistrationToken(Node $node, AuditLogger $audit)
    {
        $token = 'hpreg_' . Str::random(64);

        $node->update([
            'registration_token' => hash('sha256', $token),
            'registration_token_expires_at' => now()->addHours(24),
            'is_registered' => false,
            'registered_at' => null,
        ]);

        $audit->log(
            AuditEvent::NODE_UPDATED,
            null,
            "Node \"{$node->name}\" registration token was generated.",
            ['node_id' => $node->id]
        );

        return back()->with([
            'registration_token' => $token,
        ]);
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'location' => ['nullable', 'string', 'max:255'],

            'public_fqdn' => ['required', 'string', 'max:255'],
            'internal_fqdn' => ['nullable', 'string', 'max:255'],

            'scheme' => ['required', 'string', 'in:http,https'],
            'daemon_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'sftp_port' => ['required', 'integer', 'min:1', 'max:65535'],

            'api_token' => ['sometimes', 'nullable', 'string', 'max:500'],

            'behind_proxy' => ['sometimes', 'boolean'],
            'maintenance_mode' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],

            'cpu_threads' => ['nullable', 'numeric', 'min:0'],
            'memory_mib' => ['nullable', 'integer', 'min:0'],
            'memory_overallocate' => ['required', 'integer', 'min:0', 'max:1000'],
            'disk_mib' => ['nullable', 'integer', 'min:0'],
            'disk_overallocate' => ['required', 'integer', 'min:0', 'max:1000'],
            'max_upload_mib' => ['required', 'integer', 'min:1', 'max:1024'],
        ]);
    }

    private function nodePayload(Node $node): array
    {
        return [
            'id' => $node->id,
            'name' => $node->name,
            'description' => $node->description,
            'location' => $node->location,

            'public_fqdn' => $node->public_fqdn,
            'internal_fqdn' => $node->internal_fqdn,
            'fqdn' => $node->public_fqdn ?: $node->fqdn,

            'scheme' => $node->scheme,
            'daemon_port' => $node->daemon_port,
            'sftp_port' => $node->sftp_port,
            'port' => $node->daemon_port,

            'behind_proxy' => $node->behind_proxy,
            'maintenance_mode' => $node->maintenance_mode,
            'is_active' => $node->is_active,

            'is_registered' => $node->is_registered,
            'registered_at' => $node->registered_at?->toISOString(),
            'last_seen_at' => $node->last_seen_at?->toISOString(),
            'worker_version' => $node->worker_version,
            'worker_hostname' => $node->worker_hostname,
            'worker_platform' => $node->worker_platform,
            'worker_ip' => $node->worker_ip,

            'cpu_threads' => $node->cpu_threads,
            'memory_mib' => $node->memory_mib,
            'memory_overallocate' => $node->memory_overallocate,
            'disk_mib' => $node->disk_mib,
            'disk_overallocate' => $node->disk_overallocate,
            'max_upload_mib' => $node->max_upload_mib,

            'public_url' => $node->publicUrl(),
            'base_url' => $node->baseUrl(),

            'live_stat' => $node->liveStat ? [
                'host_cpu_used' => $node->liveStat->host_cpu_used,
                'host_cpu_max' => $node->liveStat->host_cpu_max,
                'host_memory_used_gb' => $node->liveStat->host_memory_used_gb,
                'host_memory_max_gb' => $node->liveStat->host_memory_max_gb,
                'host_disk_used_gb' => $node->liveStat->host_disk_used_gb,
                'host_disk_max_gb' => $node->liveStat->host_disk_max_gb,
                'cells_cpu_used' => $node->liveStat->cells_cpu_used,
                'cells_memory_used_gb' => $node->liveStat->cells_memory_used_gb,
                'cells_disk_used_gb' => $node->liveStat->cells_disk_used_gb,
                'cells_total' => $node->liveStat->cells_total,
                'cells_running' => $node->liveStat->cells_running,
                'updated_at' => $node->liveStat->updated_at?->toISOString(),
            ] : null,

            'created_at' => $node->created_at?->toISOString(),
            'updated_at' => $node->updated_at?->toISOString(),
        ];
    }

    private function bool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}