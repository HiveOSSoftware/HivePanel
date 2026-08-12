<?php

namespace App\Services\Migrations;

use App\Models\Comb;
use App\Models\NodeAllocation;
use App\Models\PlatformMigration;
use App\Models\PlatformMigrationServer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class MigrationPreparationService
{
    public function prepare(PlatformMigration $migration): array
    {
        $migration->load([
            'servers' => fn ($query) => $query
                ->where('selected', true)
                ->with([
                    'destinationNode',
                    'destinationOwner',
                ])
                ->orderBy('source_node_name')
                ->orderBy('name'),
        ]);

        if ($migration->servers->isEmpty()) {
            throw new RuntimeException('No selected migration servers are available to prepare.');
        }

        foreach ($migration->servers as $server) {
            if ((bool) data_get($server->execution_plan, 'blocked', false)) {
                throw new RuntimeException(
                    "Server '{$server->name}' is blocked by preflight and cannot be prepared."
                );
            }
        }

        return DB::transaction(function () use ($migration) {
            $createdUsers = [];
            $createdCombs = [];
            $preparedServers = 0;

            $ownerCache = [];
            $combCache = [];

            foreach ($migration->servers as $server) {
                $owner = $this->resolveOwner(
                    $server,
                    $ownerCache,
                    $createdUsers,
                );

                $comb = $this->resolveComb(
                    $server,
                    $combCache,
                    $createdCombs,
                );

                $this->prepareAllocations($server);

                $server->forceFill([
                    'destination_owner_id' => $owner->id,
                    'owner_strategy' => 'existing',
                    'owner_create_data' => null,

                    'destination_comb' => $comb->external_id,
                    'comb_strategy' => 'existing',
                    'comb_create_data' => null,

                    'status' => 'prepared',
                    'current_stage' => 'Execution prepared',
                    'progress' => 0,
                    'error' => null,
                    'prepared_at' => now(),
                ])->save();

                $preparedServers++;
            }

            $migration->forceFill([
                'status' => 'execution_ready',
                'current_stage' => 'Execution preparation complete',
                'progress' => 100,
                'error' => null,
            ])->save();

            return [
                'prepared_servers' => $preparedServers,
                'created_users' => array_values($createdUsers),
                'created_combs' => array_values($createdCombs),
            ];
        });
    }

    private function resolveOwner(
        PlatformMigrationServer $server,
        array &$cache,
        array &$createdUsers,
    ): User {
        if ($server->owner_strategy !== 'create') {
            $owner = $server->destinationOwner;

            if (! $owner) {
                throw new RuntimeException(
                    "Destination owner is missing for '{$server->name}'."
                );
            }

            return $owner;
        }

        $data = $server->owner_create_data ?? [];

        $email = mb_strtolower(
            trim((string) ($data['email'] ?? ''))
        );

        $name = trim((string) ($data['name'] ?? ''));

        if ($email === '' || $name === '') {
            throw new RuntimeException(
                "New user details are incomplete for '{$server->name}'."
            );
        }

        if (isset($cache[$email])) {
            return $cache[$email];
        }

        $existing = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($existing) {
            $cache[$email] = $existing;

            return $existing;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(Str::random(64)),
        ]);

        $cache[$email] = $user;

        $createdUsers[$email] = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];

        return $user;
    }

    private function resolveComb(
        PlatformMigrationServer $server,
        array &$cache,
        array &$createdCombs,
    ): Comb {
        if ($server->comb_strategy !== 'create') {
            $comb = Comb::query()
                ->where(
                    'external_id',
                    $server->destination_comb,
                )
                ->first();

            if (! $comb) {
                throw new RuntimeException(
                    "Destination Comb is missing for '{$server->name}'."
                );
            }

            return $comb;
        }

        $data = $server->comb_create_data ?? [];

        $externalId = trim(
            (string) ($data['external_id'] ?? '')
        );

        if ($externalId === '') {
            throw new RuntimeException(
                "Draft Comb external ID is missing for '{$server->name}'."
            );
        }

        if (isset($cache[$externalId])) {
            return $cache[$externalId];
        }

        $existing = Comb::query()
            ->where('external_id', $externalId)
            ->first();

        if ($existing) {
            $cache[$externalId] = $existing;

            return $existing;
        }

        $comb = Comb::create([
            'external_id' => $externalId,
            'name' => (string) (
                $data['name']
                ?? $server->source_egg_name
                ?? $externalId
            ),
            'game' => (string) (
                $data['game']
                ?? $server->source_egg_name
                ?? 'Imported'
            ),
            'source' => (string) (
                $data['source']
                ?? 'pterodactyl-migration'
            ),
            'data' => $this->buildCombData(
                $server,
                $data,
            ),
        ]);

        $cache[$externalId] = $comb;

        $createdCombs[$externalId] = [
            'id' => $comb->id,
            'external_id' => $comb->external_id,
            'name' => $comb->name,
            'game' => $comb->game,
        ];

        return $comb;
    }

    private function buildCombData(
        PlatformMigrationServer $server,
        array $data,
    ): array {
        $externalId = trim(
            (string) (
                $data['external_id']
                ?? $server->destination_comb
                ?? ''
            )
        );

        $image = $data['image']
            ?? $data['docker_image']
            ?? data_get(
                $server->source_metadata,
                'docker_image',
            );

        return [
            'id' => $externalId,
            'name' => $data['name']
                ?? $server->source_egg_name
                ?? $externalId,
            'game' => $data['game']
                ?? $server->source_egg_name
                ?? 'imported',
            'tags' => array_values(
                array_unique([
                    'migration',
                    'pterodactyl',
                    ...((array) ($data['tags'] ?? [])),
                ])
            ),
            'image' => $image,
            'working_dir' => $data['working_dir']
                ?? '/home/container',
            'entrypoint' => array_values(
                (array) (
                    $data['entrypoint']
                    ?? []
                )
            ),
            'environment' => $this->normaliseEnvironment(
                (array) (
                    $data['environment']
                    ?? data_get(
                        $server->source_metadata,
                        'environment',
                        [],
                    )
                )
            ),
            'mounts' => array_values(
                (array) (
                    $data['mounts']
                    ?? [
                        [
                            'source' => 'instance',
                            'target' => '/home/container',
                        ],
                    ]
                )
            ),
            'startup' => (string) (
                $data['startup']
                ?? data_get(
                    $server->source_metadata,
                    'startup',
                    ''
                )
            ),
            'variables_schema' => array_values(
                (array) (
                    $data['variables_schema']
                    ?? []
                )
            ),
            'install' => array_values(
                (array) (
                    $data['install']
                    ?? []
                )
            ),

            'migration' => [
                'source' => 'pterodactyl',
                'source_server_id' => $server->source_server_id,
                'source_uuid' => $server->source_uuid,
                'nest_id' => data_get(
                    $server->source_metadata,
                    'nest_id',
                ),
                'egg_id' => data_get(
                    $server->source_metadata,
                    'egg_id',
                ),
                'egg_name' => $server->source_egg_name,
                'egg' => data_get(
                    $server->source_metadata,
                    'source_egg',
                    [],
                ),
            ],
        ];
    }

    private function normaliseEnvironment(
        array $environment,
    ): array {
        return collect($environment)
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
    }

    private function prepareAllocations(
        PlatformMigrationServer $server,
    ): void {
        $plan = $server->execution_plan ?? [];

        $allocations = collect(
            $plan['allocations'] ?? []
        );

        foreach ($allocations as $allocationPlan) {
            $action = $allocationPlan['action'] ?? null;

            $destination = $allocationPlan['destination']
                ?? null;

            if (! is_array($destination)) {
                throw new RuntimeException(
                    "Allocation preparation is incomplete for '{$server->name}'."
                );
            }

            if ($action === 'create_exact') {
                $allocation = NodeAllocation::firstOrCreate(
                    [
                        'node_id' => $server->destination_node_id,
                        'ip' => $destination['ip'],
                        'port' => (int) $destination['port'],
                    ],
                    [
                        'alias' => $destination['alias']
                            ?? null,
                        'is_reserved' => true,
                    ]
                );

                if (
                    filled($allocation->cell_id)
                    || (
                        (bool) $allocation->is_reserved
                        && ! $allocation->wasRecentlyCreated
                    )
                ) {
                    throw new RuntimeException(
                        "Allocation {$allocation->ip}:{$allocation->port} became unavailable before preparation completed."
                    );
                }

                $allocation->forceFill([
                    'is_reserved' => true,
                ])->save();

                continue;
            }

            if (
                ! in_array(
                    $action,
                    [
                        'preserve_existing',
                        'replace_private',
                        'replace_unavailable_ip',
                        'allocate_new',
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    "Unsupported allocation preparation action '{$action}' for '{$server->name}'."
                );
            }

            $allocation = NodeAllocation::query()
                ->where('id', $destination['id'])
                ->where('node_id', $server->destination_node_id)
                ->lockForUpdate()
                ->first();

            if (! $allocation) {
                throw new RuntimeException(
                    "Destination allocation is missing for '{$server->name}'."
                );
            }

            if (
                filled($allocation->cell_id)
                || (bool) $allocation->is_reserved
            ) {
                throw new RuntimeException(
                    "Allocation {$allocation->ip}:{$allocation->port} became unavailable before preparation completed."
                );
            }

            $allocation->forceFill([
                'is_reserved' => true,
            ])->save();
        }
    }
}