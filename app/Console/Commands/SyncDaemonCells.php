<?php

namespace App\Console\Commands;

use App\Models\Cell;
use App\Models\Node;
use App\Models\User;
use App\Services\Node\CellNodeClient;
use Illuminate\Console\Command;

class SyncDaemonCells extends Command
{
    protected $signature = 'hive:sync-cells
        {--owner= : User ID or email to assign as owner}
        {--node= : Node ID or node name to sync from}';

    protected $description = 'Sync existing worker cells into the panel database.';

    public function handle(CellNodeClient $client): int
    {
        $owner = $this->resolveOwner();
        $node = $this->resolveNode();

        if (! $owner) {
            $this->error('Owner not found. Use --owner=user@example.com or --owner=1');

            return self::FAILURE;
        }

        if (! $node) {
            $this->error('Node not found. Create a node first or use --node="Local Node".');

            return self::FAILURE;
        }

        $response = $client->cells($node);

        if (($response['error'] ?? false) === true) {
            $this->error($response['message'] ?? 'Node is unavailable.');

            return self::FAILURE;
        }

        $workerCells = $response['cells'] ?? $response;

        if (! is_array($workerCells)) {
            $this->error('Invalid node response.');

            return self::FAILURE;
        }

        $count = 0;

        foreach ($workerCells as $workerCell) {
            $daemonId = $workerCell['id'] ?? null;

            if (! $daemonId) {
                continue;
            }

            Cell::query()->updateOrCreate(
                [
                    'node_id' => $node->id,
                    'daemon_id' => $daemonId,
                ],
                [
                    'owner_id' => $owner->id,
                    'name' => $workerCell['name'] ?? $daemonId,
                    'comb' => $workerCell['comb'] ?? null,
                    'metadata' => $workerCell,
                ]
            );

            $count++;
        }

        $this->info("Synced {$count} cell(s).");
        $this->info("Owner: {$owner->email}");
        $this->info("Node: {$node->name}");
        $this->info("Worker URL: {$node->baseUrl()}");

        return self::SUCCESS;
    }

    private function resolveOwner(): ?User
    {
        $owner = $this->option('owner');

        if (! $owner) {
            return User::query()->first();
        }

        if (is_numeric($owner)) {
            return User::query()->find((int) $owner);
        }

        return User::query()
            ->where('email', $owner)
            ->first();
    }

    private function resolveNode(): ?Node
    {
        $node = $this->option('node');

        if (! $node) {
            return Node::query()->first();
        }

        return Node::query()
            ->where('id', $node)
            ->orWhere('name', $node)
            ->orWhere('public_fqdn', $node)
            ->orWhere('internal_fqdn', $node)
            ->orWhere('fqdn', $node)
            ->first();
    }
}