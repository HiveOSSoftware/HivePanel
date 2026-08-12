<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\NodeAllocation;
use App\Services\Node\NodeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

class AdminNodeAllocationController extends Controller
{
    public function index(Node $node)
    {
        return Inertia::render('Admin/Nodes/Allocations', [
            'node' => [
                'id' => $node->id,
                'name' => $node->name,
                'location' => $node->location,
                'fqdn' => $node->public_fqdn,
                'public_fqdn' => $node->public_fqdn,
                'scheme' => $node->scheme,
                'daemon_port' => $node->daemon_port,
                'sftp_port' => $node->sftp_port,
                'maintenance_mode' => $node->maintenance_mode,
                'is_active' => $node->is_active,
                'is_registered' => $node->is_registered,
                'last_seen_at' => $node->last_seen_at?->toISOString(),
            ],

            'allocations' => $node->allocations()
                ->with('cell:id,name,primary_allocation_id')
                ->orderBy('ip')
                ->orderBy('port')
                ->get()
                ->map(fn (NodeAllocation $allocation) => [
                    'id' => $allocation->id,
                    'ip' => $allocation->ip,
                    'port' => $allocation->port,
                    'alias' => $allocation->alias,
                    'notes' => $allocation->notes,
                    'is_reserved' => (bool) $allocation->is_reserved,
                    'is_assigned' => filled($allocation->cell_id),
                    'is_primary' => $allocation->cell
                        ? (string) $allocation->cell->primary_allocation_id === (string) $allocation->id
                        : false,
                    'cell' => $allocation->cell ? [
                        'id' => $allocation->cell->id,
                        'name' => $allocation->cell->name,
                        'primary_allocation_id' => $allocation->cell->primary_allocation_id,
                    ] : null,
                    'created_at' => $allocation->created_at?->toISOString(),
                ])
                ->values(),
        ]);
    }

    public function store(Node $node, Request $request, NodeClient $nodeClient)
    {
        $data = $request->validate([
            'ip' => ['required', 'ip', 'max:255'],
            'alias' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'port_start' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'port_end' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ]);

        $ip = trim($data['ip']);

        $alias = filled($data['alias'] ?? null)
            ? trim($data['alias'])
            : null;

        $notes = filled($data['notes'] ?? null)
            ? trim($data['notes'])
            : null;

        if (! empty($data['port'])) {
            $ports = [
                (int) $data['port'],
            ];
        } else {
            abort_if(
                empty($data['port_start']) || empty($data['port_end']),
                422,
                'Port or port range is required.'
            );

            abort_if(
                $data['port_end'] < $data['port_start'],
                422,
                'Port end must be greater than or equal to port start.'
            );

            $ports = range(
                (int) $data['port_start'],
                (int) $data['port_end'],
            );
        }

        try {
            DB::transaction(function () use (
                $node,
                $nodeClient,
                $ip,
                $alias,
                $notes,
                $ports,
            ): void {
                foreach ($ports as $port) {
                    NodeAllocation::firstOrCreate(
                        [
                            'node_id' => $node->id,
                            'ip' => $ip,
                            'port' => $port,
                        ],
                        [
                            'alias' => $alias,
                            'notes' => $notes,
                        ]
                    );
                }

                $this->syncWorkerAllocations(
                    $node,
                    $nodeClient,
                );
            });
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'allocations' => $exception->getMessage()
                        ?: 'The allocation pool could not be updated.',
                ]);
        }

        return back()->with(
            'success',
            count($ports) === 1
                ? 'Allocation added successfully.'
                : count($ports) . ' allocations added successfully.'
        );
    }

    public function reserve(Node $node, NodeAllocation $allocation)
    {
        abort_unless(
            (string) $allocation->node_id === (string) $node->id,
            404
        );

        abort_if(
            filled($allocation->cell_id),
            422,
            'Assigned allocations cannot be reserved.'
        );

        $allocation->update([
            'is_reserved' => ! $allocation->is_reserved,
        ]);

        return back();
    }

    public function destroy(
        Node $node,
        NodeAllocation $allocation,
        NodeClient $nodeClient,
    ) {
        abort_unless(
            (string) $allocation->node_id === (string) $node->id,
            404
        );

        abort_if(
            filled($allocation->cell_id),
            422,
            'Cannot delete an assigned allocation.'
        );

        $remainingAllocationCount = $node->allocations()
            ->where('id', '!=', $allocation->id)
            ->count();

        abort_if(
            $node->is_registered &&
            filled($node->api_token) &&
            $remainingAllocationCount === 0,
            422,
            'The final allocation cannot be removed while the Worker is registered.'
        );

        try {
            DB::transaction(function () use (
                $node,
                $allocation,
                $nodeClient,
            ): void {
                $allocation->delete();

                $this->syncWorkerAllocations(
                    $node,
                    $nodeClient,
                );
            });
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'allocations' => $exception->getMessage()
                    ?: 'The allocation could not be deleted.',
            ]);
        }

        return back()->with(
            'success',
            'Allocation deleted successfully.'
        );
    }

    private function syncWorkerAllocations(
        Node $node,
        NodeClient $nodeClient,
    ): void {
        $allocations = $node->allocations()
            ->orderBy('ip')
            ->orderBy('port')
            ->get([
                'ip',
                'port',
            ])
            ->map(fn (NodeAllocation $allocation) => [
                'ip' => $allocation->ip,
                'port' => (int) $allocation->port,
            ])
            ->values()
            ->all();

        if (
            ! $node->is_registered ||
            blank($node->api_token) ||
            count($allocations) === 0
        ) {
            return;
        }

        $nodeClient->updateAllocationConfiguration(
            $node,
            $allocations,
        );
    }
}