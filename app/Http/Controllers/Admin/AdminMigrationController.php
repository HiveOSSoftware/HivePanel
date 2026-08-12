<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Migrations\DiscoverPlatformMigration;
use App\Models\Comb;
use App\Models\Node;
use App\Models\PlatformMigration;
use App\Models\PlatformMigrationServer;
use App\Models\User;
use App\Services\Migrations\MigrationDatabaseTransferConfigurationService;
use App\Services\Migrations\MigrationDiscoveryService;
use App\Services\Migrations\MigrationPlanningService;
use App\Services\Migrations\MigrationPreparationService;
use App\Services\Migrations\MigrationTransferConfigurationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminMigrationController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Migrations/Index', [
            'migrations' => PlatformMigration::query()
                ->withCount('servers')
                ->latest()
                ->get()
                ->map(fn (PlatformMigration $migration) => [
                    'id' => $migration->id,
                    'name' => $migration->name,
                    'source_type' => $migration->source_type,
                    'status' => $migration->status,
                    'current_stage' => $migration->current_stage,
                    'progress' => $migration->progress,
                    'error' => $migration->error,
                    'servers_count' => $migration->servers_count,
                    'discovered_at' => $migration->discovered_at?->toISOString(),
                    'created_at' => $migration->created_at?->toISOString(),
                ]),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Migrations/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'source_type' => ['required', 'string', 'in:pterodactyl'],
            'name' => ['required', 'string', 'max:150'],
            'panel_url' => ['required', 'url', 'max:500'],
            'api_key' => ['required', 'string', 'max:1000'],

            'database_enabled' => ['required', 'boolean'],
            'database_host' => ['nullable', 'required_if:database_enabled,true', 'string', 'max:255'],
            'database_port' => ['nullable', 'required_if:database_enabled,true', 'integer', 'min:1', 'max:65535'],
            'database_name' => ['nullable', 'required_if:database_enabled,true', 'string', 'max:255'],
            'database_username' => ['nullable', 'required_if:database_enabled,true', 'string', 'max:255'],
            'database_password' => ['nullable', 'required_if:database_enabled,true', 'string', 'max:1000'],
            'preserve_passwords' => ['required', 'boolean'],
        ]);

        $databaseConfig = [
            'enabled' => (bool) $data['database_enabled'],
            'host' => $data['database_host'] ?? '',
            'port' => (int) ($data['database_port'] ?? 3306),
            'database' => $data['database_name'] ?? '',
            'username' => $data['database_username'] ?? '',
            'password' => $data['database_password'] ?? '',
            'preserve_passwords' => (bool) $data['preserve_passwords'],
        ];

        $migration = PlatformMigration::create([
            'source_type' => $data['source_type'],
            'name' => $data['name'],
            'source_config' => [
                'panel_url' => rtrim($data['panel_url'], '/'),
                'api_key' => $data['api_key'],
                'database' => $databaseConfig,
            ],
            'status' => 'queued',
            'current_stage' => 'Waiting for discovery worker',
            'progress' => 0,
            'error' => null,
        ]);

        DiscoverPlatformMigration::dispatch($migration->id);

        return redirect()
            ->route('admin.migrations.show', $migration)
            ->with('success', 'Migration created. Source discovery has been queued.');
    }

    public function show(PlatformMigration $migration)
    {
        $migration->load([
            'servers' => fn ($query) => $query
                ->orderBy('source_node_name')
                ->orderBy('name'),
        ]);

        return Inertia::render('Admin/Migrations/Show', [
            'migration' => $this->migrationPayload($migration),
            'servers' => $this->serverPayload($migration),
        ]);
    }

    public function status(PlatformMigration $migration)
    {
        $migration->load([
            'servers' => fn ($query) => $query
                ->orderBy('source_node_name')
                ->orderBy('name'),
        ]);

        return response()->json([
            'migration' => $this->migrationPayload($migration),
            'servers' => $this->serverPayload($migration),
        ]);
    }

    public function discover(PlatformMigration $migration)
    {
        if (in_array($migration->status, ['queued', 'discovering'], true)) {
            return back()->with('error', 'Discovery is already running for this migration.');
        }

        $migration->forceFill([
            'status' => 'queued',
            'current_stage' => 'Waiting for discovery worker',
            'progress' => 0,
            'error' => null,
        ])->save();

        DiscoverPlatformMigration::dispatch($migration->id);

        return back()->with('success', 'Source discovery has been queued.');
    }

    public function mapping(PlatformMigration $migration)
    {
        abort_unless(
            in_array($migration->status, ['ready', 'mapped'], true),
            422,
            'Source discovery must complete before mapping can be configured.'
        );

        $migration->load([
            'servers' => fn ($query) => $query
                ->orderBy('source_node_name')
                ->orderBy('name'),
        ]);

        $nodes = Node::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'location',
            ]);

        $users = User::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
            ]);

        $combs = Comb::query()
            ->orderBy('game')
            ->orderBy('name')
            ->get()
            ->map(fn (Comb $comb) => [
                'id' => $comb->id,
                'external_id' => $comb->external_id,
                'name' => $comb->name,
                'game' => $comb->game,
                'source' => $comb->source,
            ])
            ->values();

        $sourceOwners = $migration->servers
            ->pluck('owner_email')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->map(function (string $email) use ($migration, $users) {
                $existing = $migration->servers
                    ->first(fn (PlatformMigrationServer $server) =>
                        strcasecmp((string) $server->owner_email, $email) === 0
                        && filled($server->destination_owner_id)
                    );

                $suggested = $existing?->destination_owner_id
                    ?: $users->first(
                        fn (User $user) =>
                            strcasecmp((string) $user->email, $email) === 0
                    )?->id;

                $sourceServer = $migration->servers
                    ->first(fn (PlatformMigrationServer $server) =>
                        strcasecmp((string) $server->owner_email, $email) === 0
                    );

                $sourceUser = (array) data_get(
                    $sourceServer?->source_metadata,
                    'source_user',
                    [],
                );

                $sourceName = trim(
                    implode(' ', array_filter([
                        $sourceUser['first_name'] ?? null,
                        $sourceUser['last_name'] ?? null,
                    ]))
                );

                if ($sourceName === '') {
                    $sourceName = Str::of($email)
                        ->before('@')
                        ->replace(['.', '_', '-'], ' ')
                        ->title()
                        ->toString();
                }

                return [
                    'source' => $email,
                    'key' => $this->mappingKey($email),
                    'destination_id' => $suggested,
                    'matched' => filled($suggested),
                    'create_name' => $sourceName,
                    'database' => $this->databaseOwnerPayload(
                        $migration,
                        $email,
                    ),
                ];
            });

        $sourceNodes = $migration->servers
            ->pluck('source_node_name')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->map(function (string $name) use ($migration, $nodes) {
                $existing = $migration->servers
                    ->first(fn (PlatformMigrationServer $server) =>
                        strcasecmp((string) $server->source_node_name, $name) === 0
                        && filled($server->destination_node_id)
                    );

                $suggested = $existing?->destination_node_id
                    ?: $nodes->first(
                        fn (Node $node) =>
                            strcasecmp((string) $node->name, $name) === 0
                    )?->id;

                return [
                    'source' => $name,
                    'key' => $this->mappingKey($name),
                    'destination_id' => $suggested,
                    'matched' => filled($suggested),
                ];
            });

        $sourceEggs = $migration->servers
            ->pluck('source_egg_name')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->map(function (string $name) use ($migration, $combs) {
                $existing = $migration->servers
                    ->first(fn (PlatformMigrationServer $server) =>
                        strcasecmp((string) $server->source_egg_name, $name) === 0
                        && filled($server->destination_comb)
                    );

                $suggested = $existing?->destination_comb
                    ?: $this->suggestCombExternalId($name, $combs->all());

                $sourceServer = $migration->servers
                    ->first(fn (PlatformMigrationServer $server) =>
                        strcasecmp((string) $server->source_egg_name, $name) === 0
                    );

                $eggMetadata = (array) data_get(
                    $sourceServer?->source_metadata,
                    'source_egg',
                    [],
                );

                $externalId = Str::of($name)
                    ->lower()
                    ->replaceMatches('/[^a-z0-9]+/', '-')
                    ->trim('-')
                    ->prepend('migrated-')
                    ->toString();

                return [
                    'source' => $name,
                    'key' => $this->mappingKey($name),
                    'destination_external_id' => $suggested,
                    'matched' => filled($suggested),
                    'create' => [
                        'name' => $name,
                        'game' => $name,
                        'external_id' => $externalId,
                        'source' => 'pterodactyl-migration',
                        'docker_image' => data_get(
                            $sourceServer?->source_metadata,
                            'docker_image',
                        ),
                        'startup' => data_get(
                            $sourceServer?->source_metadata,
                            'startup',
                        ),
                        'environment' => data_get(
                            $sourceServer?->source_metadata,
                            'environment',
                            [],
                        ),
                        'pterodactyl' => [
                            'nest_id' => data_get(
                                $sourceServer?->source_metadata,
                                'nest_id',
                            ),
                            'egg_id' => data_get(
                                $sourceServer?->source_metadata,
                                'egg_id',
                            ),
                            'egg' => $eggMetadata,
                        ],
                    ],
                ];
            });

        return Inertia::render('Admin/Migrations/Map', [
            'migration' => $this->migrationPayload($migration),
            'servers' => $this->serverPayload($migration),
            'nodes' => $nodes,
            'users' => $users,
            'combs' => $combs,
            'sourceOwners' => $sourceOwners,
            'sourceNodes' => $sourceNodes,
            'sourceEggs' => $sourceEggs,
        ]);
    }

    public function updateMapping(
        PlatformMigration $migration,
        Request $request,
    ) {
        abort_unless(
            in_array($migration->status, ['ready', 'mapped'], true),
            422,
            'Source discovery must complete before mapping can be updated.'
        );

        $data = $request->validate([
            'selected_server_ids' => ['present', 'array'],
            'selected_server_ids.*' => [
                'string',
                'exists:platform_migration_servers,id',
            ],

            'owner_transfer' => ['present', 'array'],
            'owner_transfer.*' => ['boolean'],

            'owner_map' => ['present', 'array'],
            'owner_map.*' => ['nullable', 'string'],

            'owner_create' => ['present', 'array'],
            'owner_create.*.name' => ['nullable', 'string', 'max:255'],
            'owner_create.*.email' => ['nullable', 'email', 'max:255'],
            'owner_create.*.preserve_password' => ['nullable', 'boolean'],

            'node_map' => ['present', 'array'],
            'node_map.*' => ['nullable', 'exists:nodes,id'],

            'comb_map' => ['present', 'array'],
            'comb_map.*' => ['nullable', 'string'],

            'comb_create' => ['present', 'array'],
            'comb_create.*.name' => ['nullable', 'string', 'max:255'],
            'comb_create.*.game' => ['nullable', 'string', 'max:255'],
            'comb_create.*.external_id' => ['nullable', 'string', 'max:255'],
            'comb_create.*.source' => ['nullable', 'string', 'max:255'],
            'comb_create.*.docker_image' => ['nullable', 'string', 'max:1000'],
            'comb_create.*.startup' => ['nullable', 'string', 'max:5000'],
            'comb_create.*.environment' => ['nullable', 'array'],
            'comb_create.*.pterodactyl' => ['nullable', 'array'],

            'database_selection' => ['present', 'array'],
            'database_selection.*' => ['array'],
            'database_selection.*.*' => ['string'],

            'allocation_strategy' => [
                'required',
                'string',
                'in:preserve,allocate_new',
            ],
        ]);

        $selectedIds = collect($data['selected_server_ids'])
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        $servers = $migration->servers()->get();

        foreach ($servers as $server) {
            $selected = $selectedIds->contains((string) $server->id);

            $ownerKey = $server->owner_email
                ? $this->mappingKey($server->owner_email)
                : null;

            $transferOwner = $ownerKey
                ? (bool) data_get(
                    $data,
                    'owner_transfer.' . $ownerKey,
                    true,
                )
                : false;

            $ownerSelection = $ownerKey
                ? data_get($data, 'owner_map.' . $ownerKey)
                : null;

            $ownerStrategy = $transferOwner
                && $ownerSelection === '__create__'
                    ? 'create'
                    : 'existing';

            $ownerId = $ownerStrategy === 'existing'
                ? $ownerSelection
                : null;

            $ownerCreateData = $ownerStrategy === 'create' && $ownerKey
                ? (array) data_get(
                    $data,
                    'owner_create.' . $ownerKey,
                    [],
                )
                : null;

            $nodeId = $server->source_node_name
                ? data_get(
                    $data,
                    'node_map.' . $this->mappingKey($server->source_node_name)
                )
                : null;

            $combKey = $server->source_egg_name
                ? $this->mappingKey($server->source_egg_name)
                : null;

            $combSelection = $combKey
                ? data_get($data, 'comb_map.' . $combKey)
                : null;

            $combStrategy = $combSelection === '__create__'
                ? 'create'
                : 'existing';

            $combExternalId = $combStrategy === 'existing'
                ? $combSelection
                : null;

            $combCreateData = $combStrategy === 'create' && $combKey
                ? (array) data_get(
                    $data,
                    'comb_create.' . $combKey,
                    [],
                )
                : null;

            $selectedDatabaseIds = collect(
                $data['database_selection'][$server->id]
                ?? []
            )
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->values();

            $databasePlan = collect(
                (array) data_get(
                    $server->source_metadata,
                    'databases',
                    [],
                )
            )
                ->map(function (
                    array $database
                ) use ($selectedDatabaseIds) {
                    $databaseId = (string) (
                        $database['id']
                        ?? $database['database']
                        ?? ''
                    );

                    return [
                        'source' => $database,
                        'selected' => $selectedDatabaseIds
                            ->contains($databaseId),
                        'status' => 'pending',
                        'destination' => null,
                        'error' => null,
                    ];
                })
                ->values()
                ->all();

            if ($selected) {
                if ($ownerStrategy === 'existing') {
                    if (blank($ownerId) || ! User::query()->whereKey($ownerId)->exists()) {
                        return back()->withErrors([
                            'mapping' => "Selected server '{$server->name}' does not have a valid destination owner.",
                        ]);
                    }
                } else {
                    if (
                        blank($ownerCreateData['name'] ?? null)
                        || blank($ownerCreateData['email'] ?? null)
                    ) {
                        return back()->withErrors([
                            'mapping' => "Selected server '{$server->name}' is configured to create an owner, but the new user details are incomplete.",
                        ]);
                    }

                    if (
                        User::query()
                            ->whereRaw('LOWER(email) = ?', [
                                mb_strtolower((string) $ownerCreateData['email']),
                            ])
                            ->exists()
                    ) {
                        return back()->withErrors([
                            'mapping' => "A HivePanel user already exists with email {$ownerCreateData['email']}. Map that existing user instead.",
                        ]);
                    }
                }

                if (blank($nodeId)) {
                    return back()->withErrors([
                        'mapping' => "Selected server '{$server->name}' does not have a destination node.",
                    ]);
                }

                if ($combStrategy === 'existing') {
                    if (
                        blank($combExternalId)
                        || ! Comb::query()
                            ->where('external_id', $combExternalId)
                            ->exists()
                    ) {
                        return back()->withErrors([
                            'mapping' => "Selected server '{$server->name}' does not have a valid destination Comb.",
                        ]);
                    }
                } else {
                    if (
                        blank($combCreateData['name'] ?? null)
                        || blank($combCreateData['game'] ?? null)
                        || blank($combCreateData['external_id'] ?? null)
                    ) {
                        return back()->withErrors([
                            'mapping' => "Selected server '{$server->name}' is configured to create a Comb, but its draft definition is incomplete.",
                        ]);
                    }

                    if (
                        Comb::query()
                            ->where(
                                'external_id',
                                $combCreateData['external_id'],
                            )
                            ->exists()
                    ) {
                        return back()->withErrors([
                            'mapping' => "Comb external ID '{$combCreateData['external_id']}' already exists. Choose the existing Comb or change the draft external ID.",
                        ]);
                    }
                }
            }

            $server->forceFill([
                'selected' => $selected,
                'destination_owner_id' => $ownerId ?: null,
                'owner_strategy' => $ownerStrategy,
                'owner_create_data' => $ownerCreateData ?: null,
                'transfer_owner' => $transferOwner,
                'destination_node_id' => $nodeId ?: null,
                'destination_comb' => $combExternalId ?: null,
                'comb_strategy' => $combStrategy,
                'comb_create_data' => $combCreateData ?: null,
                'allocation_strategy' => $data['allocation_strategy'],
                'database_plan' => $databasePlan,
                'status' => $selected ? 'mapped' : 'skipped',
                'current_stage' => $selected ? 'Ready for review' : 'Skipped',
                'error' => null,
            ])->save();
        }

        $migration->forceFill([
            'status' => 'mapped',
            'current_stage' => 'Destination mapping complete',
            'progress' => 100,
            'error' => null,
        ])->save();

        return redirect()
            ->route('admin.migrations.review', $migration)
            ->with('success', 'Migration mapping saved successfully.');
    }

    public function review(PlatformMigration $migration)
    {
        abort_unless(
            $migration->status === 'mapped',
            422,
            'Complete destination mapping before reviewing this migration.'
        );

        $migration->load([
            'servers' => fn ($query) => $query
                ->where('selected', true)
                ->with([
                    'destinationNode:id,name,location',
                    'destinationOwner:id,name,email',
                ])
                ->orderBy('source_node_name')
                ->orderBy('name'),
        ]);

        $combLookup = Comb::query()
            ->get()
            ->keyBy('external_id');

        $servers = $migration->servers
            ->map(function (PlatformMigrationServer $server) use ($combLookup) {
                $comb = $combLookup->get($server->destination_comb);

                return [
                    ...$this->migrationServerPayload($server),
                    'destination_node' => $server->destinationNode ? [
                        'id' => $server->destinationNode->id,
                        'name' => $server->destinationNode->name,
                        'location' => $server->destinationNode->location,
                    ] : null,
                    'destination_owner' => $server->owner_strategy === 'create'
                        ? [
                            'id' => null,
                            'name' => data_get(
                                $server->owner_create_data,
                                'name',
                            ),
                            'email' => data_get(
                                $server->owner_create_data,
                                'email',
                            ),
                            'will_create' => true,
                        ]
                        : ($server->destinationOwner ? [
                            'id' => $server->destinationOwner->id,
                            'name' => $server->destinationOwner->name,
                            'email' => $server->destinationOwner->email,
                            'will_create' => false,
                        ] : null),
                    'destination_comb_record' => $server->comb_strategy === 'create'
                        ? [
                            'id' => null,
                            'external_id' => data_get(
                                $server->comb_create_data,
                                'external_id',
                            ),
                            'name' => data_get(
                                $server->comb_create_data,
                                'name',
                            ),
                            'game' => data_get(
                                $server->comb_create_data,
                                'game',
                            ),
                            'will_create' => true,
                        ]
                        : ($comb ? [
                            'id' => $comb->id,
                            'external_id' => $comb->external_id,
                            'name' => $comb->name,
                            'game' => $comb->game,
                            'will_create' => false,
                        ] : null),
                ];
            })
            ->values();

        return Inertia::render('Admin/Migrations/Review', [
            'migration' => $this->migrationPayload($migration),
            'servers' => $servers,
            'summary' => [
                'selected' => $servers->count(),
                'preserve_allocations' => $servers
                    ->where('allocation_strategy', 'preserve')
                    ->count(),
                'allocate_new' => $servers
                    ->where('allocation_strategy', 'allocate_new')
                    ->count(),
                'source_nodes' => $servers
                    ->pluck('source_node_name')
                    ->filter()
                    ->unique()
                    ->count(),
                'owners' => $servers
                    ->pluck('destination_owner.email')
                    ->filter()
                    ->unique()
                    ->count(),
                'users_to_create' => $migration->servers
                    ->where('owner_strategy', 'create')
                    ->pluck('owner_create_data.email')
                    ->filter()
                    ->unique()
                    ->count(),
                'combs_to_create' => $migration->servers
                    ->where('comb_strategy', 'create')
                    ->pluck('comb_create_data.external_id')
                    ->filter()
                    ->unique()
                    ->count(),
                'users_to_transfer' => $migration->servers
                    ->where('transfer_owner', true)
                    ->pluck('owner_email')
                    ->filter()
                    ->unique()
                    ->count(),
                'databases_to_transfer' => $migration->servers
                    ->sum(function (PlatformMigrationServer $server) {
                        return collect(
                            $server->database_plan ?? []
                        )
                            ->where('selected', true)
                            ->count();
                    }),
            ],
        ]);
    }

    public function preflight(
        PlatformMigration $migration,
        MigrationPlanningService $planner,
        MigrationTransferConfigurationService $transfer,
        MigrationDatabaseTransferConfigurationService $databaseTransfer,
    ) {
        abort_unless(
            in_array(
                $migration->status,
                [
                    'mapped',
                    'preflight_ready',
                    'preflight_blocked',
                ],
                true
            ),
            422,
            'Complete migration mapping before running preflight.'
        );

        $summary = $planner->plan($migration);

        $migration->refresh()->load([
            'servers' => fn ($query) => $query
                ->where('selected', true)
                ->with([
                    'destinationNode:id,name,location',
                    'destinationOwner:id,name,email',
                ])
                ->orderBy('source_node_name')
                ->orderBy('name'),
        ]);

        $sourceNodes = $migration->servers
            ->pluck('source_node_name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return Inertia::render('Admin/Migrations/Preflight', [
            'migration' => $this->migrationPayload($migration),
            'servers' => $migration->servers
                ->map(fn (PlatformMigrationServer $server) => [
                    ...$this->migrationServerPayload($server),
                    'destination_node' => $server->destinationNode ? [
                        'id' => $server->destinationNode->id,
                        'name' => $server->destinationNode->name,
                        'location' => $server->destinationNode->location,
                    ] : null,
                    'destination_owner' => $server->owner_strategy === 'create'
                        ? [
                            'id' => null,
                            'name' => data_get(
                                $server->owner_create_data,
                                'name',
                            ),
                            'email' => data_get(
                                $server->owner_create_data,
                                'email',
                            ),
                            'will_create' => true,
                        ]
                        : ($server->destinationOwner ? [
                            'id' => $server->destinationOwner->id,
                            'name' => $server->destinationOwner->name,
                            'email' => $server->destinationOwner->email,
                            'will_create' => false,
                        ] : null),
                ])
                ->values(),
            'summary' => $summary,
            'transferNodes' => $transfer->frontend(
                $migration,
                $sourceNodes,
            ),
            'transferComplete' => $transfer->complete(
                $migration,
                $sourceNodes,
            ),
            'databaseTransferHosts' => $databaseTransfer->frontend(
                $migration,
            ),
            'databaseTransferComplete' => $databaseTransfer->complete(
                $migration,
            ),
            'selectedDatabaseCount' => $databaseTransfer->selectedDatabaseCount(
                $migration,
            ),
        ]);
    }

    public function updateTransferConfiguration(
        PlatformMigration $migration,
        Request $request,
        MigrationTransferConfigurationService $transfer,
    ) {
        abort_unless(
            in_array(
                $migration->status,
                [
                    'mapped',
                    'preflight_ready',
                    'preflight_blocked',
                ],
                true
            ),
            422,
            'Complete mapping before configuring transfer access.'
        );

        $data = $request->validate([
            'nodes' => ['required', 'array'],
            'nodes.*.protocol' => [
                'required',
                'string',
                'in:sftp,ftp,ftps',
            ],
            'nodes.*.host' => [
                'required',
                'string',
                'max:255',
            ],
            'nodes.*.port' => [
                'required',
                'integer',
                'min:1',
                'max:65535',
            ],
            'nodes.*.username' => [
                'required',
                'string',
                'max:255',
            ],
            'nodes.*.password' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'nodes.*.path_template' => [
                'required',
                'string',
                'max:1500',
            ],
        ]);

        $transfer->update(
            $migration,
            $data['nodes'],
        );

        return back()->with(
            'success',
            'Source transfer access saved successfully.'
        );
    }

    public function updateDatabaseTransferConfiguration(
        PlatformMigration $migration,
        Request $request,
        MigrationDatabaseTransferConfigurationService $databaseTransfer,
    ) {
        abort_unless(
            in_array(
                $migration->status,
                [
                    'mapped',
                    'preflight_ready',
                    'preflight_blocked',
                ],
                true
            ),
            422,
            'Complete mapping before configuring database transfer.'
        );

        $data = $request->validate([
            'hosts' => ['required', 'array'],

            'hosts.*.source_username' => [
                'required',
                'string',
                'max:255',
            ],
            'hosts.*.source_password' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'hosts.*.destination_host' => [
                'required',
                'string',
                'max:255',
            ],
            'hosts.*.destination_port' => [
                'required',
                'integer',
                'min:1',
                'max:65535',
            ],
            'hosts.*.destination_username' => [
                'required',
                'string',
                'max:255',
            ],
            'hosts.*.destination_password' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'hosts.*.destination_prefix' => [
                'required',
                'string',
                'max:100',
            ],
        ]);

        $databaseTransfer->update(
            $migration,
            $data['hosts'],
        );

        return back()->with(
            'success',
            'Database transfer hosts tested and saved successfully.'
        );
    }

    public function updateDatabaseSource(
        PlatformMigration $migration,
        Request $request,
        MigrationDiscoveryService $discovery,
    ) {
        if (in_array($migration->status, ['queued', 'discovering'], true)) {
            return back()->withErrors([
                'database' => 'Database settings cannot be changed while discovery is running.',
            ]);
        }

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'host' => ['nullable', 'required_if:enabled,true', 'string', 'max:255'],
            'port' => ['nullable', 'required_if:enabled,true', 'integer', 'min:1', 'max:65535'],
            'database' => ['nullable', 'required_if:enabled,true', 'string', 'max:255'],
            'username' => ['nullable', 'required_if:enabled,true', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:1000'],
            'preserve_passwords' => ['required', 'boolean'],
        ]);

        $sourceConfig = $migration->source_config ?? [];
        $current = (array) ($sourceConfig['database'] ?? []);

        $password = filled($data['password'] ?? null)
            ? $data['password']
            : ($current['password'] ?? '');

        if ((bool) $data['enabled'] && blank($password)) {
            return back()->withErrors([
                'password' => 'A database password is required.',
            ]);
        }

        $sourceConfig['database'] = [
            'enabled' => (bool) $data['enabled'],
            'host' => $data['host'] ?? '',
            'port' => (int) ($data['port'] ?? 3306),
            'database' => $data['database'] ?? '',
            'username' => $data['username'] ?? '',
            'password' => $password,
            'preserve_passwords' => (bool) $data['preserve_passwords'],
        ];

        unset($sourceConfig['database_discovery']);

        $migration->forceFill([
            'source_config' => $sourceConfig,
        ])->save();

        if ((bool) $data['enabled']) {
            try {
                $discovery->databaseSource($migration)->testConnection();
            } catch (\Throwable $exception) {
                report($exception);

                return back()->withErrors([
                    'database' => $exception->getMessage(),
                ]);
            }
        }

        return back()->with(
            'success',
            (bool) $data['enabled']
                ? 'Pterodactyl database connection saved and verified.'
                : 'Pterodactyl database enhancement disabled.'
        );
    }

    public function prepare(
        PlatformMigration $migration,
        MigrationPreparationService $preparation,
        MigrationTransferConfigurationService $transfer,
        MigrationDatabaseTransferConfigurationService $databaseTransfer,
    ) {
        abort_unless(
            $migration->status === 'preflight_ready',
            422,
            'Migration preflight must be ready before execution can be prepared.'
        );

        $sourceNodes = $migration->servers()
            ->where('selected', true)
            ->pluck('source_node_name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        abort_unless(
            $transfer->complete(
                $migration,
                $sourceNodes,
            ),
            422,
            'Source transfer access must be configured before execution can be prepared.'
        );

        abort_unless(
            $databaseTransfer->complete($migration),
            422,
            'Database transfer hosts must be configured before execution can be prepared.'
        );

        try {
            $result = $preparation->prepare($migration);
        } catch (\Throwable $exception) {
            report($exception);

            $migration->forceFill([
                'status' => 'preflight_blocked',
                'current_stage' => 'Execution preparation failed',
                'error' => $exception->getMessage(),
            ])->save();

            return back()->withErrors([
                'preparation' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            "Prepared {$result['prepared_servers']} server(s), created "
            . count($result['created_users'])
            . ' user(s) and '
            . count($result['created_combs'])
            . ' Comb(s).'
        );
    }

    public function updateSource(
        PlatformMigration $migration,
        Request $request,
    ) {
        if (in_array($migration->status, ['queued', 'discovering'], true)) {
            return back()->withErrors([
                'source' => 'Source credentials cannot be changed while discovery is running.',
            ]);
        }

        $data = $request->validate([
            'panel_url' => ['required', 'url', 'max:500'],
            'api_key' => ['nullable', 'string', 'max:1000'],
        ]);

        $sourceConfig = $migration->source_config ?? [];

        $sourceConfig['panel_url'] = rtrim($data['panel_url'], '/');

        if (filled($data['api_key'] ?? null)) {
            $sourceConfig['api_key'] = $data['api_key'];
        }

        if (blank($sourceConfig['api_key'] ?? null)) {
            return back()->withErrors([
                'api_key' => 'An Application API key is required.',
            ]);
        }

        $migration->forceFill([
            'source_config' => $sourceConfig,
            'status' => 'queued',
            'current_stage' => 'Waiting for discovery worker',
            'progress' => 0,
            'error' => null,
        ])->save();

        DiscoverPlatformMigration::dispatch($migration->id);

        return back()->with(
            'success',
            'Source connection updated. Discovery has been queued again.'
        );
    }

    public function destroy(PlatformMigration $migration)
    {
        $migration->delete();

        return redirect()
            ->route('admin.migrations.index')
            ->with('success', 'Migration removed successfully.');
    }

    private function migrationPayload(PlatformMigration $migration): array
    {
        return [
            'id' => $migration->id,
            'name' => $migration->name,
            'source_type' => $migration->source_type,
            'status' => $migration->status,
            'current_stage' => $migration->current_stage,
            'progress' => $migration->progress,
            'error' => $migration->error,
            'discovered_at' => $migration->discovered_at?->toISOString(),
            'created_at' => $migration->created_at?->toISOString(),
            'panel_url' => data_get($migration->source_config, 'panel_url'),
            'database' => [
                'enabled' => (bool) data_get(
                    $migration->source_config,
                    'database.enabled',
                    false,
                ),
                'host' => data_get(
                    $migration->source_config,
                    'database.host',
                ),
                'port' => (int) data_get(
                    $migration->source_config,
                    'database.port',
                    3306,
                ),
                'database' => data_get(
                    $migration->source_config,
                    'database.database',
                ),
                'username' => data_get(
                    $migration->source_config,
                    'database.username',
                ),
                'has_password' => filled(data_get(
                    $migration->source_config,
                    'database.password',
                )),
                'preserve_passwords' => (bool) data_get(
                    $migration->source_config,
                    'database.preserve_passwords',
                    false,
                ),
                'discovered_users' => count(
                    (array) data_get(
                        $migration->source_config,
                        'database_discovery.users',
                        [],
                    )
                ),
                'server_database_count' => (int) data_get(
                    $migration->source_config,
                    'database_discovery.server_database_count',
                    0,
                ),
            ],
        ];
    }

    private function serverPayload(PlatformMigration $migration)
    {
        return $migration->servers
            ->map(fn (PlatformMigrationServer $server) =>
                $this->migrationServerPayload($server)
            )
            ->values();
    }

    private function migrationServerPayload(
        PlatformMigrationServer $server,
    ): array {
        return [
            'id' => $server->id,
            'source_server_id' => $server->source_server_id,
            'source_uuid' => $server->source_uuid,
            'name' => $server->name,
            'owner_email' => $server->owner_email,
            'source_node_name' => $server->source_node_name,
            'source_egg_name' => $server->source_egg_name,
            'source_allocations' => $server->source_allocations ?? [],
            'source_metadata' => $server->source_metadata ?? [],
            'source_database_count' => count(
                (array) data_get(
                    $server->source_metadata,
                    'databases',
                    [],
                )
            ),
            'selected' => (bool) $server->selected,
            'destination_node_id' => $server->destination_node_id,
            'destination_owner_id' => $server->destination_owner_id,
            'owner_strategy' => $server->owner_strategy,
            'owner_create_data' => $server->owner_create_data ?? [],
            'transfer_owner' => (bool) $server->transfer_owner,
            'destination_comb' => $server->destination_comb,
            'comb_strategy' => $server->comb_strategy,
            'comb_create_data' => $server->comb_create_data ?? [],
            'allocation_strategy' => $server->allocation_strategy,
            'execution_plan' => $server->execution_plan ?? [],
            'database_plan' => $server->database_plan ?? [],
            'status' => $server->status,
            'current_stage' => $server->current_stage,
            'progress' => $server->progress,
            'error' => $server->error,
            'prepared_at' => $server->prepared_at?->toISOString(),
        ];
    }

    private function suggestCombExternalId(
        string $eggName,
        array $combs,
    ): ?string {
        $needle = $this->normaliseMappingName($eggName);

        $exact = collect($combs)->first(function (array $comb) use ($needle) {
            return in_array(
                $needle,
                [
                    $this->normaliseMappingName((string) ($comb['name'] ?? '')),
                    $this->normaliseMappingName((string) ($comb['external_id'] ?? '')),
                ],
                true
            );
        });

        if ($exact) {
            return $exact['external_id'];
        }

        $contains = collect($combs)->first(function (array $comb) use ($needle) {
            $values = [
                $this->normaliseMappingName((string) ($comb['name'] ?? '')),
                $this->normaliseMappingName((string) ($comb['game'] ?? '')),
                $this->normaliseMappingName((string) ($comb['external_id'] ?? '')),
            ];

            foreach ($values as $value) {
                if (
                    $value !== ''
                    && (
                        str_contains($value, $needle)
                        || str_contains($needle, $value)
                    )
                ) {
                    return true;
                }
            }

            return false;
        });

        return $contains['external_id'] ?? null;
    }

    private function normaliseMappingName(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->toString();
    }

    private function databaseOwnerPayload(
        PlatformMigration $migration,
        string $email,
    ): array {
        $key = $this->mappingKey($email);

        $user = (array) data_get(
            $migration->source_config,
            'database_discovery.users.' . $key,
            [],
        );

        return [
            'available' => count($user) > 0,
            'password_available' => filled(
                $user['password_hash'] ?? null
            ),
            'password_compatible' => (bool) (
                $user['password_compatible']
                ?? false
            ),
            'password_hash_type' => $user[
                'password_hash_type'
            ] ?? null,
            'preserve_by_default' => (bool) data_get(
                $migration->source_config,
                'database.preserve_passwords',
                false,
            ),
        ];
    }

    private function mappingKey(string $value): string
    {
        return sha1(mb_strtolower(trim($value)));
    }
}