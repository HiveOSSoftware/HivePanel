<?php

namespace App\Services\Migrations;

use App\Models\NodeAllocation;
use App\Models\PlatformMigration;
use App\Models\PlatformMigrationServer;
use Illuminate\Support\Collection;

class MigrationPlanningService
{
    public function plan(PlatformMigration $migration): array
    {
        $migration->load([
            'servers' => fn ($query) => $query
                ->where('selected', true)
                ->with('destinationNode'),
        ]);

        $ready = 0;
        $warnings = 0;
        $blocked = 0;

        foreach ($migration->servers as $server) {
            $plan = $this->planServer($server);

            $server->forceFill([
                'execution_plan' => $plan,
                'status' => $plan['blocked']
                    ? 'blocked'
                    : 'planned',
                'current_stage' => $plan['blocked']
                    ? 'Preflight blocked'
                    : 'Preflight complete',
                'error' => $plan['blocked']
                    ? implode(' ', $plan['errors'])
                    : null,
            ])->save();

            if ($plan['blocked']) {
                $blocked++;
            } elseif (count($plan['warnings']) > 0) {
                $warnings++;
            } else {
                $ready++;
            }
        }

        $migration->forceFill([
            'status' => $blocked > 0
                ? 'preflight_blocked'
                : 'preflight_ready',
            'current_stage' => $blocked > 0
                ? 'Preflight requires attention'
                : 'Preflight complete',
            'progress' => 100,
            'error' => null,
        ])->save();

        return [
            'total' => $migration->servers->count(),
            'ready' => $ready,
            'warnings' => $warnings,
            'blocked' => $blocked,
        ];
    }

    private function planServer(
        PlatformMigrationServer $server,
    ): array {
        $node = $server->destinationNode;

        if (! $node) {
            return [
                'blocked' => true,
                'errors' => [
                    'Destination node is missing.',
                ],
                'warnings' => [],
                'allocations' => [],
                'resources' => $this->resourcePlan($server),
            ];
        }

        $sourceAllocations = collect(
            $server->source_allocations ?? []
        );

        $availableAllocations = $node->allocations()
            ->whereNull('cell_id')
            ->where('is_reserved', false)
            ->orderBy('ip')
            ->orderBy('port')
            ->get();

        $errors = [];
        $warnings = [];

        $allocationPlans = $server->allocation_strategy === 'allocate_new'
            ? $this->planNewAllocations(
                $sourceAllocations,
                $availableAllocations,
                $errors,
            )
            : $this->planPreservedAllocations(
                $node->id,
                $sourceAllocations,
                $availableAllocations,
                $errors,
                $warnings,
            );

        return [
            'blocked' => count($errors) > 0,
            'errors' => $errors,
            'warnings' => $warnings,
            'allocations' => $allocationPlans,
            'resources' => $this->resourcePlan($server),
            'transfer' => [
                'source_node' => $server->source_node_name,
                'source_uuid' => $server->source_uuid,
            ],
        ];
    }

    private function planNewAllocations(
        Collection $sourceAllocations,
        Collection $availableAllocations,
        array &$errors,
    ): array {
        if ($sourceAllocations->count() > $availableAllocations->count()) {
            $errors[] = 'The destination node does not have enough free allocations for this server.';

            return [];
        }

        return $sourceAllocations
            ->values()
            ->map(function (
                array $source,
                int $index,
            ) use ($availableAllocations) {
                $destination = $availableAllocations->values()->get($index);

                return $this->allocationPlan(
                    $source,
                    'allocate_new',
                    $destination ? [
                        'id' => $destination->id,
                        'ip' => $destination->ip,
                        'port' => $destination->port,
                        'alias' => $destination->alias,
                    ] : null,
                );
            })
            ->all();
    }

    private function planPreservedAllocations(
        string $nodeId,
        Collection $sourceAllocations,
        Collection $availableAllocations,
        array &$errors,
        array &$warnings,
    ): array {
        $plans = [];
        $replacementPool = $availableAllocations
            ->values();

        foreach ($sourceAllocations->values() as $source) {
            $ip = trim((string) ($source['ip'] ?? ''));
            $port = (int) ($source['port'] ?? 0);

            if (! $this->isPublicIp($ip)) {
                $replacement = $replacementPool->shift();

                if (! $replacement) {
                    $errors[] = "No free destination allocation is available to replace private source allocation {$ip}:{$port}.";

                    $plans[] = $this->allocationPlan(
                        $source,
                        'replacement_required',
                        null,
                        'Private/internal Pterodactyl allocation cannot be preserved.',
                    );

                    continue;
                }

                $warnings[] = "{$ip}:{$port} is private/internal and will be replaced.";

                $plans[] = $this->allocationPlan(
                    $source,
                    'replace_private',
                    [
                        'id' => $replacement->id,
                        'ip' => $replacement->ip,
                        'port' => $replacement->port,
                        'alias' => $replacement->alias,
                    ],
                    'Private/internal Pterodactyl allocation will use a free HivePanel allocation.',
                );

                continue;
            }

            $exact = NodeAllocation::query()
                ->where('node_id', $nodeId)
                ->where('ip', $ip)
                ->where('port', $port)
                ->first();

            if ($exact) {
                if (
                    filled($exact->cell_id)
                    || (bool) $exact->is_reserved
                ) {
                    $errors[] = "Source allocation {$ip}:{$port} exists on the destination node but is not available.";

                    $plans[] = $this->allocationPlan(
                        $source,
                        'conflict',
                        [
                            'id' => $exact->id,
                            'ip' => $exact->ip,
                            'port' => $exact->port,
                            'alias' => $exact->alias,
                        ],
                        'Matching destination allocation is already assigned or reserved.',
                    );

                    continue;
                }

                $replacementPool = $replacementPool
                    ->reject(
                        fn (NodeAllocation $allocation) =>
                            (string) $allocation->id ===
                            (string) $exact->id
                    )
                    ->values();

                $plans[] = $this->allocationPlan(
                    $source,
                    'preserve_existing',
                    [
                        'id' => $exact->id,
                        'ip' => $exact->ip,
                        'port' => $exact->port,
                        'alias' => $exact->alias,
                    ],
                );

                continue;
            }

            $sameIpExists = NodeAllocation::query()
                ->where('node_id', $nodeId)
                ->where('ip', $ip)
                ->exists();

            if ($sameIpExists) {
                $warnings[] = "{$ip}:{$port} does not exist yet but the destination node already uses {$ip}; it can be created during execution.";

                $plans[] = $this->allocationPlan(
                    $source,
                    'create_exact',
                    [
                        'id' => null,
                        'ip' => $ip,
                        'port' => $port,
                        'alias' => $source['alias'] ?? null,
                    ],
                    'Exact allocation will be created during execution.',
                );

                continue;
            }

            $replacement = $replacementPool->shift();

            if (! $replacement) {
                $errors[] = "Source IP {$ip} is not configured on the destination node and there is no free replacement allocation.";

                $plans[] = $this->allocationPlan(
                    $source,
                    'replacement_required',
                    null,
                    'Source IP is not currently represented by the destination node.',
                );

                continue;
            }

            $warnings[] = "Source IP {$ip} is not configured on the destination node; {$ip}:{$port} will be replaced.";

            $plans[] = $this->allocationPlan(
                $source,
                'replace_unavailable_ip',
                [
                    'id' => $replacement->id,
                    'ip' => $replacement->ip,
                    'port' => $replacement->port,
                    'alias' => $replacement->alias,
                ],
                'Source IP is unavailable on the destination node.',
            );
        }

        return $plans;
    }

    private function allocationPlan(
        array $source,
        string $action,
        ?array $destination,
        ?string $message = null,
    ): array {
        return [
            'source' => [
                'id' => $source['id'] ?? null,
                'ip' => $source['ip'] ?? null,
                'port' => isset($source['port'])
                    ? (int) $source['port']
                    : null,
                'alias' => $source['alias'] ?? null,
                'is_primary' => (bool) (
                    $source['is_default']
                    ?? false
                ),
            ],
            'action' => $action,
            'destination' => $destination,
            'message' => $message,
        ];
    }

    private function resourcePlan(
        PlatformMigrationServer $server,
    ): array {
        $limits = data_get(
            $server->source_metadata,
            'limits',
            [],
        );

        return [
            'memory_mb' => (int) ($limits['memory'] ?? 0),
            'swap_mb' => (int) ($limits['swap'] ?? 0),
            'disk_mb' => (int) ($limits['disk'] ?? 0),
            'cpu_percent' => (int) ($limits['cpu'] ?? 0),
            'io_weight' => max(
                10,
                min(
                    1000,
                    (int) ($limits['io'] ?? 500),
                ),
            ),
            'cpu_pinning' => $limits['threads'] ?? null,
            'database_limit' => data_get(
                $server->source_metadata,
                'feature_limits.databases',
            ),
            'allocation_limit' => data_get(
                $server->source_metadata,
                'feature_limits.allocations',
            ),
            'backup_limit' => data_get(
                $server->source_metadata,
                'feature_limits.backups',
            ),
            'docker_image' => data_get(
                $server->source_metadata,
                'docker_image',
            ),
            'startup_command' => data_get(
                $server->source_metadata,
                'startup',
            ),
            'environment' => data_get(
                $server->source_metadata,
                'environment',
                [],
            ),
        ];
    }

    private function isPublicIp(string $ip): bool
    {
        if (
            filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4
            ) === false
            && filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV6
            ) === false
        ) {
            return false;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE
            | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}