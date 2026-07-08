<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Jobs\InstallCellJob;
use App\Models\Comb;
use App\Models\Cell;
use App\Models\Node;
use App\Models\NodeAllocation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Node\CellNodeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminCellController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Cells/Index', [
            'cells' => Cell::query()
                ->with([
                    'owner:id,name,email',
                    'node:id,name,location',
                    'allocation:id,cell_id,ip,port,alias',
                ])
                ->latest()
                ->get()
                ->map(fn (Cell $cell) => $this->cellPayload($cell)),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Cells/Create', [
            'nodes' => Node::query()
                ->where('is_active', true)
                ->withCount([
                    'allocations as available_allocations_count' => fn ($query) => $query
                        ->whereNull('cell_id')
                        ->where('is_reserved', false),
                ])
                ->orderBy('name')
                ->get()
                ->map(fn (Node $node) => [
                    'id' => $node->id,
                    'name' => $node->name,
                    'location' => $node->location,
                    'public_fqdn' => $node->public_fqdn,
                    'available_allocations_count' => $node->available_allocations_count,
                ]),

            'combs' => Comb::query()
                ->orderBy('game')
                ->orderBy('name')
                ->get()
                ->map(fn (Comb $comb) => [
                    'id' => $comb->id,
                    'external_id' => $comb->external_id,
                    'name' => $comb->name,
                    'game' => $comb->game,
                    'source' => $comb->source,
                    'data' => $comb->data,
                ]),
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function allocations(Node $node)
    {
        return response()->json([
            'allocations' => $node->allocations()
                ->whereNull('cell_id')
                ->where('is_reserved', false)
                ->orderBy('ip')
                ->orderBy('port')
                ->get()
                ->map(fn (NodeAllocation $allocation) => [
                    'id' => $allocation->id,
                    'ip' => $allocation->ip,
                    'port' => $allocation->port,
                    'alias' => $allocation->alias,
                    'label' => "{$allocation->ip}:{$allocation->port}" . ($allocation->alias ? " ({$allocation->alias})" : ''),
                ]),
        ]);
    }

    public function store(Request $request, CellNodeClient $cells, AuditLogger $audit)
    {
        $data = $request->validate([
            'node_id' => ['required', 'exists:nodes,id'],
            'allocation_id' => ['required', 'exists:node_allocations,id'],
            'additional_allocation_ids' => ['nullable', 'array'],
            'additional_allocation_ids.*' => ['integer', 'exists:node_allocations,id'],

            'name' => ['required', 'string', 'max:255'],
            'owner_email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_on_completion' => ['boolean'],

            'comb_id' => ['required', 'exists:combs,id'],
            'version' => ['required', 'string', 'max:255'],
            'skip_install_script' => ['boolean'],

            'memory_mb' => ['required', 'integer', 'min:0'],
            'overhead_memory_mb' => ['required', 'integer', 'min:0'],
            'swap_mb' => ['required', 'integer', 'min:-1'],
            'disk_mb' => ['required', 'integer', 'min:0'],
            'cpu_percent' => ['required', 'integer', 'min:0', 'max:1000'],
            'cpu_pinning' => ['nullable', 'string', 'max:255'],
            'io_weight' => ['required', 'integer', 'min:10', 'max:1000'],
            'oom_killer' => ['boolean'],
            'exclude_from_resource_calculation' => ['boolean'],

            'database_limit' => ['nullable', 'integer', 'min:0'],
            'allocation_limit' => ['nullable', 'integer', 'min:0'],
            'backup_limit' => ['nullable', 'integer', 'min:0'],
            'backup_storage_mb' => ['nullable', 'integer', 'min:0'],

            'docker_image' => ['nullable', 'string', 'max:500'],
            'startup_command' => ['nullable', 'string', 'max:1000'],

            'variables' => ['nullable', 'array'],
        ]);

        return DB::transaction(function () use ($request, $data, $cells, $audit) {
            $node = Node::query()
                ->where('id', $data['node_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $allocation = NodeAllocation::query()
                ->where('id', $data['allocation_id'])
                ->where('node_id', $node->id)
                ->whereNull('cell_id')
                ->where('is_reserved', false)
                ->lockForUpdate()
                ->firstOrFail();

            $additionalIds = collect($data['additional_allocation_ids'] ?? [])
                ->filter(fn ($id) => (int) $id !== (int) $allocation->id)
                ->unique()
                ->values();

            $additionalAllocations = NodeAllocation::query()
                ->whereIn('id', $additionalIds)
                ->where('node_id', $node->id)
                ->whereNull('cell_id')
                ->where('is_reserved', false)
                ->lockForUpdate()
                ->get();

            abort_if(
                $additionalAllocations->count() !== $additionalIds->count(),
                422,
                'One or more additional allocations are not available.'
            );

            $comb = Comb::findOrFail($data['comb_id']);

            $variables = [
                ...(array) ($data['variables'] ?? []),
                'memory' => (string) $data['memory_mb'],
                'version' => $data['version'],
                'server_port' => (string) $allocation->port,
                'server_ip' => $allocation->ip,
            ];

            $daemonCell = $cells->createCell($node, [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'comb' => $comb->external_id,
                'comb_data' => $comb->data,

                'allocation' => [
                    'ip' => $allocation->ip,
                    'port' => $allocation->port,
                    'alias' => $allocation->alias,
                ],

                'additional_allocations' => $additionalAllocations->map(fn ($extra) => [
                    'ip' => $extra->ip,
                    'port' => $extra->port,
                    'alias' => $extra->alias,
                ])->values()->all(),

                'variables' => $variables,

                'limits' => [
                    'memory_mb' => $data['memory_mb'],
                    'overhead_memory_mb' => $data['overhead_memory_mb'],
                    'swap_mb' => $data['swap_mb'],
                    'disk_mb' => $data['disk_mb'],
                    'cpu_percent' => $data['cpu_percent'],
                    'cpu_pinning' => $data['cpu_pinning'] ?? null,
                    'io_weight' => $data['io_weight'],
                    'oom_killer' => $data['oom_killer'],
                ],

                'feature_limits' => [
                    'database_limit' => $data['database_limit'] ?? null,
                    'allocation_limit' => $data['allocation_limit'] ?? null,
                    'backup_limit' => $data['backup_limit'] ?? null,
                    'backup_storage_mb' => $data['backup_storage_mb'] ?? null,
                ],

                'docker' => [
                    'image' => $data['docker_image'] ?? null,
                ],

                'startup' => [
                    'command' => $data['startup_command'] ?? null,
                    'skip_install_script' => $data['skip_install_script'],
                    'start_on_completion' => $data['start_on_completion'],
                ],
            ]);

            $owner = ! empty($data['owner_email'])
                ? User::where('email', $data['owner_email'])->firstOrFail()
                : $request->user();

            $cell = Cell::create([
                'node_id' => $node->id,
                'owner_id' => $owner->id,
                'daemon_id' => $daemonCell['id'] ?? null,
                'name' => $daemonCell['name'] ?? $data['name'],
                'comb' => $daemonCell['comb'] ?? $comb->external_id,
                'metadata' => [
                    ...$daemonCell,

                    'description' => $data['description'] ?? null,

                    'comb_id' => $comb->id,
                    'comb_data' => $comb->data,

                    'allocation' => [
                        'id' => $allocation->id,
                        'ip' => $allocation->ip,
                        'port' => $allocation->port,
                        'alias' => $allocation->alias,
                        'primary' => true,
                    ],

                    'additional_allocations' => $additionalAllocations->map(fn ($extra) => [
                        'id' => $extra->id,
                        'ip' => $extra->ip,
                        'port' => $extra->port,
                        'alias' => $extra->alias,
                    ])->values()->all(),

                    'limits' => [
                        'memory_mb' => $data['memory_mb'],
                        'overhead_memory_mb' => $data['overhead_memory_mb'],
                        'swap_mb' => $data['swap_mb'],
                        'disk_mb' => $data['disk_mb'],
                        'cpu_percent' => $data['cpu_percent'],
                        'cpu_pinning' => $data['cpu_pinning'] ?? null,
                        'io_weight' => $data['io_weight'],
                        'oom_killer' => $data['oom_killer'],
                        'exclude_from_resource_calculation' => $data['exclude_from_resource_calculation'],
                    ],

                    'feature_limits' => [
                        'database_limit' => $data['database_limit'] ?? null,
                        'allocation_limit' => $data['allocation_limit'] ?? null,
                        'backup_limit' => $data['backup_limit'] ?? null,
                        'backup_storage_mb' => $data['backup_storage_mb'] ?? null,
                    ],

                    'docker' => [
                        'image' => $data['docker_image'] ?? null,
                    ],

                    'startup' => [
                        'command' => $data['startup_command'] ?? null,
                        'skip_install_script' => $data['skip_install_script'],
                        'start_on_completion' => $data['start_on_completion'],
                    ],

                    'variables' => $variables,
                ],
            ]);

            $allocation->update([
                'cell_id' => $cell->id,
            ]);

            $additionalAllocations->each->update([
                'cell_id' => $cell->id,
            ]);

            if (! $data['skip_install_script']) {
                InstallCellJob::dispatch(
                    $cell->id,
                    (bool) $data['start_on_completion']
                );
            } elseif ($data['start_on_completion']) {
                $cells->startCell($cell);
            }

            $audit->log(
                AuditEvent::SERVER_CREATED,
                $cell,
                "Server \"{$cell->name}\" was created.",
                [
                    'node_id' => $node->id,
                    'daemon_id' => $cell->daemon_id,
                    'comb' => $cell->comb,
                    'allocation_id' => $allocation->id,
                    'allocation' => "{$allocation->ip}:{$allocation->port}",
                ]
            );

            return redirect()->route('admin.cells.index');
        });
    }

    public function show(Cell $cell)
    {
        $cell->load([
            'owner:id,name,email',
            'node:id,name,location,public_fqdn',
            'allocation:id,cell_id,ip,port,alias,is_reserved',
        ]);

        return Inertia::render('Admin/Cells/Show', [
            'cell' => $this->cellPayload($cell),
        ]);
    }

    public function edit(Cell $cell)
    {
        $cell->load([
            'owner:id,name,email',
            'node:id,name,location',
            'allocation:id,cell_id,ip,port,alias',
        ]);

        return Inertia::render('Admin/Cells/Edit', [
            'cell' => $this->cellPayload($cell),
        ]);
    }

    public function update(Request $request, Cell $cell, AuditLogger $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $cell->update([
            'name' => $data['name'],
        ]);

        $audit->log(
            AuditEvent::SERVER_UPDATED,
            $cell,
            "Server \"{$cell->name}\" was updated.",
            [
                'cell_id' => $cell->id,
            ]
        );

        return redirect()->route('admin.cells.show', $cell);
    }

    public function destroy(Cell $cell, CellNodeClient $cells, AuditLogger $audit)
    {
        if ($cell->daemon_id && $cell->node) {
            $cells->deleteCell($cell);
        }

        DB::transaction(function () use ($cell, $audit) {
            $cell->allocation?->update([
                'cell_id' => null,
            ]);

            $name = $cell->name;
            $id = $cell->id;

            $cell->delete();

            $audit->log(
                AuditEvent::SERVER_DELETED,
                null,
                "Server \"{$name}\" was deleted.",
                [
                    'cell_id' => $id,
                ]
            );
        });

        return redirect()->route('admin.cells.index');
    }

    private function cellPayload(Cell $cell): array
    {
        return [
            'id' => $cell->getRouteKey(),
            'name' => $cell->name,
            'comb' => $cell->comb,
            'daemon_id' => $cell->daemon_id,

            'owner' => $cell->owner ? [
                'id' => $cell->owner->id,
                'name' => $cell->owner->name,
                'email' => $cell->owner->email,
            ] : null,

            'node' => $cell->node ? [
                'id' => $cell->node->id,
                'name' => $cell->node->name,
                'location' => $cell->node->location,
                'public_fqdn' => $cell->node->public_fqdn ?? null,
            ] : null,

            'allocation' => $cell->allocation ? [
                'id' => $cell->allocation->id,
                'ip' => $cell->allocation->ip,
                'port' => $cell->allocation->port,
                'alias' => $cell->allocation->alias,
            ] : null,

            'limits' => [
                'memory_mb' => data_get($cell->metadata, 'limits.memory_mb'),
                'disk_mb' => data_get($cell->metadata, 'limits.disk_mb'),
                'cpu_percent' => data_get($cell->metadata, 'limits.cpu_percent'),
            ],

            'variables' => data_get($cell->metadata, 'variables', []),
            'metadata' => $cell->metadata,

            'created_at' => $cell->created_at?->toISOString(),
            'updated_at' => $cell->updated_at?->toISOString(),
        ];
    }
}