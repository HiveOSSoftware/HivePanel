<?php

namespace App\Services\Cells;

use App\Enums\AuditEvent;
use App\Jobs\InstallCellJob;
use App\Models\Cell;
use App\Models\Comb;
use App\Models\Node;
use App\Models\NodeAllocation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Node\CellNodeClient;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class CellProvisioningService
{
    public function __construct(
        private readonly CellNodeClient $cells,
        private readonly AuditLogger $audit,
    ) {
    }

    public function provision(
        array $data,
        User $owner,
        array $preparedAllocationIds = [],
    ): Cell {
        $preparedAllocationIds = collect($preparedAllocationIds)
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        $workerCell = null;
        $workerNode = null;

        try {
            return DB::transaction(function () use (
                $data,
                $owner,
                $preparedAllocationIds,
                &$workerCell,
                &$workerNode,
            ): Cell {
                $node = Node::query()
                    ->where('id', $data['node_id'])
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->firstOrFail();

                $workerNode = $node;

                $allocation = NodeAllocation::query()
                    ->where('id', $data['allocation_id'])
                    ->where('node_id', $node->id)
                    ->whereNull('cell_id')
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertAllocationAvailable(
                    $allocation,
                    $preparedAllocationIds,
                );

                $additionalIds = collect(
                    $data['additional_allocation_ids']
                    ?? []
                )
                    ->map(fn ($id) => (string) $id)
                    ->filter(
                        fn ($id) =>
                            $id !== (string) $allocation->id
                    )
                    ->unique()
                    ->values();

                $additionalAllocations = NodeAllocation::query()
                    ->whereIn('id', $additionalIds)
                    ->where('node_id', $node->id)
                    ->whereNull('cell_id')
                    ->lockForUpdate()
                    ->get();

                if (
                    $additionalAllocations->count()
                    !== $additionalIds->count()
                ) {
                    throw new RuntimeException(
                        'One or more additional allocations are not available.'
                    );
                }

                foreach ($additionalAllocations as $extra) {
                    $this->assertAllocationAvailable(
                        $extra,
                        $preparedAllocationIds,
                    );
                }

                $comb = Comb::findOrFail(
                    $data['comb_id']
                );

                $combData = $this->normaliseCombData(
                    (array) $comb->data,
                    $comb,
                );

                $variables = collect(
                    (array) (
                        $data['variables']
                        ?? []
                    )
                )
                    ->mapWithKeys(function ($value, $key) {
                        if (is_bool($value)) {
                            $value = $value
                                ? 'true'
                                : 'false';
                        } elseif ($value === null) {
                            $value = '';
                        } elseif (is_scalar($value)) {
                            $value = (string) $value;
                        } else {
                            $value = json_encode(
                                $value,
                                JSON_UNESCAPED_SLASHES
                                | JSON_UNESCAPED_UNICODE
                            ) ?: '';
                        }

                        return [
                            (string) $key => $value,
                        ];
                    })
                    ->merge([
                        'memory' => (string) $data['memory_mb'],
                        'version' => (string) $data['version'],
                        'server_port' => (string) $allocation->port,
                        'server_ip' => (string) $allocation->ip,
                    ])
                    ->all();

                $workerCell = $this->cells->createCell(
                    $node,
                    [
                        'name' => $data['name'],
                        'comb' => $comb->external_id,
                        'comb_data' => $combData,

                        'allocation' => [
                            'ip' => $allocation->ip,
                            'port' => $allocation->port,
                        ],

                        'additional_allocations' =>
                            $additionalAllocations
                                ->map(fn (
                                    NodeAllocation $extra
                                ) => [
                                    'ip' => $extra->ip,
                                    'port' => $extra->port,
                                ])
                                ->values()
                                ->all(),

                        'variables' => $variables,

                        'limits' => [
                            'memory_mb' => (int) $data['memory_mb'],
                            'overhead_memory_mb' => (int) (
                                $data['overhead_memory_mb']
                                ?? 0
                            ),
                            'swap_mb' => (int) (
                                $data['swap_mb']
                                ?? 0
                            ),
                            'disk_mb' => (int) $data['disk_mb'],
                            'cpu_percent' => (int) $data['cpu_percent'],
                            'cpu_pinning' => $data['cpu_pinning']
                                ?? null,
                            'io_weight' => (int) (
                                $data['io_weight']
                                ?? 500
                            ),
                            'oom_killer' => (bool) (
                                $data['oom_killer']
                                ?? true
                            ),
                        ],

                        'feature_limits' => [
                            'database_limit' => $data[
                                'database_limit'
                            ] ?? null,
                            'allocation_limit' => $data[
                                'allocation_limit'
                            ] ?? null,
                            'backup_limit' => $data[
                                'backup_limit'
                            ] ?? null,
                            'backup_storage_mb' => $data[
                                'backup_storage_mb'
                            ] ?? null,
                        ],

                        'docker' => [
                            'image' => $data['docker_image']
                                ?? null,
                        ],

                        'startup' => [
                            'command' => $data[
                                'startup_command'
                            ] ?? null,
                        ],
                    ],
                );

                $cell = Cell::create([
                    'node_id' => $node->id,
                    'owner_id' => $owner->id,
                    'daemon_id' => $workerCell['id']
                        ?? null,
                    'name' => $workerCell['name']
                        ?? $data['name'],
                    'comb' => $workerCell['comb']
                        ?? $comb->external_id,
                    'metadata' => [
                        ...$workerCell,

                        'description' => $data['description']
                            ?? null,

                        'comb_id' => $comb->id,
                        'comb_data' => $combData,

                        'allocation' => [
                            'id' => $allocation->id,
                            'ip' => $allocation->ip,
                            'port' => $allocation->port,
                            'alias' => $allocation->alias,
                            'primary' => true,
                        ],

                        'additional_allocations' =>
                            $additionalAllocations
                                ->map(fn (
                                    NodeAllocation $extra
                                ) => [
                                    'id' => $extra->id,
                                    'ip' => $extra->ip,
                                    'port' => $extra->port,
                                    'alias' => $extra->alias,
                                ])
                                ->values()
                                ->all(),

                        'limits' => [
                            'memory_mb' => (int) $data['memory_mb'],
                            'overhead_memory_mb' => (int) (
                                $data['overhead_memory_mb']
                                ?? 0
                            ),
                            'swap_mb' => (int) (
                                $data['swap_mb']
                                ?? 0
                            ),
                            'disk_mb' => (int) $data['disk_mb'],
                            'cpu_percent' => (int) $data['cpu_percent'],
                            'cpu_pinning' => $data['cpu_pinning']
                                ?? null,
                            'io_weight' => (int) (
                                $data['io_weight']
                                ?? 500
                            ),
                            'oom_killer' => (bool) (
                                $data['oom_killer']
                                ?? true
                            ),
                            'exclude_from_resource_calculation' =>
                                (bool) (
                                    $data[
                                        'exclude_from_resource_calculation'
                                    ]
                                    ?? false
                                ),
                        ],

                        'feature_limits' => [
                            'database_limit' => $data[
                                'database_limit'
                            ] ?? null,
                            'allocation_limit' => $data[
                                'allocation_limit'
                            ] ?? null,
                            'backup_limit' => $data[
                                'backup_limit'
                            ] ?? null,
                            'backup_storage_mb' => $data[
                                'backup_storage_mb'
                            ] ?? null,
                        ],

                        'docker' => [
                            'image' => $data['docker_image']
                                ?? null,
                        ],

                        'startup' => [
                            'command' => $data[
                                'startup_command'
                            ] ?? null,
                            'skip_install_script' => (bool) (
                                $data['skip_install_script']
                                ?? false
                            ),
                            'start_on_completion' => (bool) (
                                $data['start_on_completion']
                                ?? false
                            ),
                        ],

                        'variables' => $variables,
                    ],
                ]);

                $allocation->forceFill([
                    'cell_id' => $cell->id,
                    'is_reserved' => false,
                ])->save();

                foreach ($additionalAllocations as $extra) {
                    $extra->forceFill([
                        'cell_id' => $cell->id,
                        'is_reserved' => false,
                    ])->save();
                }

                $cell->forceFill([
                    'primary_allocation_id' => $allocation->id,
                ])->save();

                if (
                    ! (bool) (
                        $data['skip_install_script']
                        ?? false
                    )
                ) {
                    InstallCellJob::dispatch(
                        $cell->id,
                        (bool) (
                            $data['start_on_completion']
                            ?? false
                        ),
                    );
                } elseif (
                    (bool) (
                        $data['start_on_completion']
                        ?? false
                    )
                ) {
                    $this->cells->startCell($cell);
                }

                $this->audit->log(
                    AuditEvent::SERVER_CREATED,
                    $cell,
                    "Server \"{$cell->name}\" was created.",
                    [
                        'node_id' => $node->id,
                        'daemon_id' => $cell->daemon_id,
                        'comb' => $cell->comb,
                        'allocation_id' => $allocation->id,
                        'allocation' =>
                            "{$allocation->ip}:{$allocation->port}",
                    ],
                );

                return $cell->fresh([
                    'node',
                    'owner',
                    'allocation',
                    'allocations',
                ]);
            });
        } catch (Throwable $exception) {
            if (
                is_array($workerCell)
                && filled($workerCell['id'] ?? null)
                && $workerNode
            ) {
                $this->cleanupWorkerCell(
                    $workerNode,
                    $workerCell,
                );
            }

            throw $exception;
        }
    }

    private function assertAllocationAvailable(
        NodeAllocation $allocation,
        $preparedAllocationIds,
    ): void {
        $prepared = $preparedAllocationIds->contains(
            (string) $allocation->id
        );

        if ($prepared) {
            if (! (bool) $allocation->is_reserved) {
                throw new RuntimeException(
                    "Prepared allocation {$allocation->ip}:{$allocation->port} is no longer reserved."
                );
            }

            return;
        }

        if ((bool) $allocation->is_reserved) {
            throw new RuntimeException(
                "Allocation {$allocation->ip}:{$allocation->port} is reserved."
            );
        }
    }

    private function normaliseCombData(
        array $combData,
        Comb $comb,
    ): array {
        $combData['id'] = trim(
            (string) (
                $combData['id']
                ?? $comb->external_id
            )
        );

        $combData['name'] = (string) (
            $combData['name']
            ?? $comb->name
            ?? $comb->external_id
        );

        $combData['game'] = (string) (
            $combData['game']
            ?? $comb->game
            ?? 'imported'
        );

        if (
            blank($combData['image'] ?? null)
            && filled($combData['docker_image'] ?? null)
        ) {
            $combData['image'] = (string) $combData['docker_image'];
        }

        $combData['working_dir'] = (string) (
            $combData['working_dir']
            ?? '/home/container'
        );

        $combData['entrypoint'] = array_values(
            (array) (
                $combData['entrypoint']
                ?? []
            )
        );

        $combData['mounts'] = array_values(
            (array) (
                $combData['mounts']
                ?? [
                    [
                        'source' => 'instance',
                        'target' => '/home/container',
                    ],
                ]
            )
        );

        $combData['startup'] = (string) (
            $combData['startup']
            ?? ''
        );

        $combData['variables_schema'] = array_values(
            (array) (
                $combData['variables_schema']
                ?? []
            )
        );

        $combData['install'] = array_values(
            (array) (
                $combData['install']
                ?? []
            )
        );

        unset($combData['docker_image']);

        $environment = (array) (
            $combData['environment']
            ?? []
        );

        $combData['environment'] = collect(
            $environment,
        )
            ->mapWithKeys(function ($value, $key) {
                if (is_bool($value)) {
                    $value = $value
                        ? 'true'
                        : 'false';
                } elseif ($value === null) {
                    $value = '';
                } elseif (is_scalar($value)) {
                    $value = (string) $value;
                } else {
                    $value = json_encode(
                        $value,
                        JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                    ) ?: '';
                }

                return [
                    (string) $key => $value,
                ];
            })
            ->all();

        return $combData;
    }

    private function cleanupWorkerCell(
        Node $node,
        array $workerCell,
    ): void {
        try {
            $temporaryCell = new Cell([
                'node_id' => $node->id,
                'daemon_id' => $workerCell['id'],
                'name' => $workerCell['name']
                    ?? 'Failed Cell',
                'comb' => $workerCell['comb']
                    ?? '',
            ]);

            $temporaryCell->setRelation(
                'node',
                $node,
            );

            $this->cells->deleteCell(
                $temporaryCell,
            );
        } catch (Throwable $cleanupException) {
            report($cleanupException);
        }
    }
}