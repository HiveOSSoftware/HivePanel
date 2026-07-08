<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\NodeAllocation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminNodeAllocationController extends Controller
{
    public function index(Node $node)
    {
        return Inertia::render('Admin/Nodes/Allocations', [
            'node' => [
                'id' => $node->id,
                'name' => $node->name,
                'location' => $node->location,
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
                ->with('cell:id,name')
                ->orderBy('ip')
                ->orderBy('port')
                ->get()
                ->map(fn (NodeAllocation $allocation) => [
                    'id' => $allocation->id,
                    'ip' => $allocation->ip,
                    'port' => $allocation->port,
                    'alias' => $allocation->alias,
                    'notes' => $allocation->notes,
                    'is_reserved' => $allocation->is_reserved,
                    'is_assigned' => filled($allocation->cell_id),
                    'cell' => $allocation->cell ? [
                        'id' => $allocation->cell->id,
                        'name' => $allocation->cell->name,
                    ] : null,
                    'created_at' => $allocation->created_at?->toISOString(),
                ]),
        ]);
    }

    public function store(Node $node, Request $request)
    {
        $data = $request->validate([
            'ip' => ['required', 'string', 'max:255'],
            'alias' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'port_start' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'port_end' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ]);

        $ip = $data['ip'];
        $alias = $data['alias'] ?? null;
        $notes = $data['notes'] ?? null;

        if (! empty($data['port'])) {
            $ports = [$data['port']];
        } else {
            abort_if(empty($data['port_start']) || empty($data['port_end']), 422, 'Port or port range is required.');
            abort_if($data['port_end'] < $data['port_start'], 422, 'Port end must be greater than port start.');

            $ports = range($data['port_start'], $data['port_end']);
        }

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

        return back();
    }

    public function reserve(Node $node, NodeAllocation $allocation)
    {
        abort_unless($allocation->node_id === $node->id, 404);
        abort_if($allocation->cell_id, 422, 'Assigned allocations cannot be reserved.');

        $allocation->update([
            'is_reserved' => ! $allocation->is_reserved,
        ]);

        return back();
    }

    public function destroy(Node $node, NodeAllocation $allocation)
    {
        abort_unless($allocation->node_id === $node->id, 404);
        abort_if($allocation->cell_id, 422, 'Cannot delete an assigned allocation.');

        $allocation->delete();

        return back();
    }
}