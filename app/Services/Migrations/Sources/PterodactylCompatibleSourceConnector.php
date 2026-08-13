<?php

namespace App\Services\Migrations\Sources;

use App\Services\Migrations\Contracts\MigrationSourceConnector;
use App\Services\Migrations\Data\DiscoveredServer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

abstract class PterodactylCompatibleSourceConnector implements MigrationSourceConnector
{
    public function __construct(
        private readonly string $panelUrl,
        private readonly string $apiKey,
    ) {
    }

    public function testConnection(): void
    {
        try {
            $response = $this->client()
                ->get('/api/application/servers', [
                    'per_page' => 1,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                $this->connectionErrorMessage($exception),
                previous: $exception,
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'HivePanel could not connect to the Pterodactyl-compatible panel: ' . $exception->getMessage(),
                previous: $exception,
            );
        }

        $this->assertSuccessfulResponse($response);
    }

    public function discoverServers(): array
    {
        $users = $this->fetchLookup(
            '/api/application/users',
            fn (array $attributes) => isset($attributes['id'])
                ? (string) $attributes['id']
                : null,
        );

        $nodes = $this->fetchLookup(
            '/api/application/nodes',
            fn (array $attributes) => isset($attributes['id'])
                ? (string) $attributes['id']
                : null,
        );

        $serverResources = $this->fetchPaginatedResources(
            '/api/application/servers',
            [
                'include' => 'allocations',
            ],
        );

        $eggs = $this->fetchEggLookup($serverResources);

        return collect($serverResources)
            ->map(fn (array $server) => $this->normaliseServer(
                $server,
                $users,
                $nodes,
                $eggs,
            ))
            ->values()
            ->all();
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->panelUrl, '/'))
            ->withToken($this->apiKey)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(20);
    }

    protected function fetchPaginatedResources(
        string $endpoint,
        array $parameters = [],
    ): array {
        $resources = [];
        $page = 1;

        do {
            try {
                $response = $this->client()
                    ->get($endpoint, [
                        ...$parameters,
                        'per_page' => 100,
                        'page' => $page,
                    ]);
            } catch (ConnectionException $exception) {
                throw new RuntimeException(
                    $this->connectionErrorMessage($exception),
                    previous: $exception,
                );
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    'HivePanel lost connection to the Pterodactyl-compatible panel while reading '
                    . $endpoint
                    . ': '
                    . $exception->getMessage(),
                    previous: $exception,
                );
            }

            $this->assertSuccessfulResponse($response);

            $payload = $response->json();

            foreach ($payload['data'] ?? [] as $resource) {
                if (is_array($resource)) {
                    $resources[] = $resource;
                }
            }

            $currentPage = (int) data_get(
                $payload,
                'meta.pagination.current_page',
                $page,
            );

            $totalPages = (int) data_get(
                $payload,
                'meta.pagination.total_pages',
                $currentPage,
            );

            $page++;
        } while ($currentPage < $totalPages);

        return $resources;
    }

    protected function fetchLookup(
        string $endpoint,
        callable $keyResolver,
    ): array {
        $lookup = [];

        foreach ($this->fetchPaginatedResources($endpoint) as $resource) {
            $attributes = $resource['attributes'] ?? $resource;

            if (! is_array($attributes)) {
                continue;
            }

            $key = $keyResolver($attributes);

            if ($key === null || $key === '') {
                continue;
            }

            $lookup[(string) $key] = $attributes;
        }

        return $lookup;
    }

    protected function tolerateMissingEggs(): bool
    {
        return false;
    }

    private function fetchEggLookup(array $servers): array
    {
        $lookup = [];
        $pairs = [];

        foreach ($servers as $resource) {
            $attributes = $resource['attributes'] ?? [];

            $nestId = isset($attributes['nest'])
                ? (string) $attributes['nest']
                : null;

            $eggId = isset($attributes['egg'])
                ? (string) $attributes['egg']
                : null;

            if (! $nestId || ! $eggId) {
                continue;
            }

            $pairs[$this->eggKey($nestId, $eggId)] = [
                'nest_id' => $nestId,
                'egg_id' => $eggId,
            ];
        }

        foreach ($pairs as $key => $pair) {
            try {
                $response = $this->client()
                    ->get(
                        '/api/application/nests/'
                        . rawurlencode($pair['nest_id'])
                        . '/eggs/'
                        . rawurlencode($pair['egg_id'])
                    );
            } catch (ConnectionException $exception) {
                throw new RuntimeException(
                    $this->connectionErrorMessage($exception),
                    previous: $exception,
                );
            } catch (Throwable $exception) {
                throw new RuntimeException(
                    'HivePanel could not read source Egg #'
                    . $pair['egg_id']
                    . ': '
                    . $exception->getMessage(),
                    previous: $exception,
                );
            }

            if ($response->status() === 404) {
                continue;
            }

            if (
                $this->tolerateMissingEggs()
                && ! $response->successful()
            ) {
                continue;
            }

            $this->assertSuccessfulResponse($response);

            $resource = $response->json();
            $attributes = $resource['attributes']
                ?? data_get($resource, 'data.attributes')
                ?? [];

            if (is_array($attributes)) {
                $lookup[$key] = $attributes;
            }
        }

        return $lookup;
    }

    protected function assertSuccessfulResponse(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = match ($response->status()) {
            401 => 'The source Application API key was rejected. Check the key and update the migration source credentials.',
            403 => 'The source Application API key does not have permission to use the Application API. Generate an Application API key with the required read permissions.',
            404 => 'The requested source Application API endpoint could not be found. Check the source panel version and URL.',
            429 => 'The Pterodactyl-compatible panel is rate limiting HivePanel. Wait a moment and retry discovery.',
            500, 502, 503, 504 => 'The Pterodactyl-compatible panel returned a server error. Check the source panel and retry discovery.',
            default => 'The Pterodactyl-compatible panel returned HTTP ' . $response->status() . '.',
        };

        $payload = $response->json();

        $apiMessage = data_get($payload, 'errors.0.detail')
            ?? data_get($payload, 'errors.0.code');

        if (filled($apiMessage)) {
            $message .= ' Source panel says: ' . $apiMessage;
        }

        throw new RuntimeException($message);
    }

    protected function connectionErrorMessage(ConnectionException $exception): string
    {
        $message = $exception->getMessage();
        $lowerMessage = strtolower($message);

        if (
            str_contains($lowerMessage, 'could not resolve host') ||
            str_contains($lowerMessage, 'name or service not known')
        ) {
            return 'HivePanel could not resolve the source panel hostname. Check the panel URL and DNS configuration.';
        }

        if (
            str_contains($lowerMessage, 'certificate') ||
            str_contains($lowerMessage, 'ssl')
        ) {
            return 'HivePanel could not verify the source panel SSL certificate. Check the source panel certificate.';
        }

        if (
            str_contains($lowerMessage, 'timed out') ||
            str_contains($lowerMessage, 'timeout')
        ) {
            return 'The connection to the source panel timed out. Check that the source panel is reachable from the HivePanel web server.';
        }

        if (str_contains($lowerMessage, 'connection refused')) {
            return 'The Pterodactyl-compatible panel refused the connection. Check its URL, port and firewall.';
        }

        return 'HivePanel could not connect to the Pterodactyl-compatible panel: ' . $message;
    }

    private function normaliseServer(
        array $resource,
        array $users,
        array $nodes,
        array $eggs,
    ): DiscoveredServer {
        $attributes = $resource['attributes'] ?? [];

        $userId = isset($attributes['user'])
            ? (string) $attributes['user']
            : null;

        $nodeId = isset($attributes['node'])
            ? (string) $attributes['node']
            : null;

        $nestId = isset($attributes['nest'])
            ? (string) $attributes['nest']
            : null;

        $eggId = isset($attributes['egg'])
            ? (string) $attributes['egg']
            : null;

        $user = $userId !== null
            ? ($users[$userId] ?? [])
            : [];

        $node = $nodeId !== null
            ? ($nodes[$nodeId] ?? [])
            : [];

        $egg = $nestId !== null && $eggId !== null
            ? ($eggs[$this->eggKey($nestId, $eggId)] ?? [])
            : [];

        $primaryAllocationId = isset($attributes['allocation'])
            ? (string) $attributes['allocation']
            : null;

        $allocationResources = $this->relationshipData(
            $resource,
            $attributes,
            'allocations',
        );

        $allocations = collect($allocationResources)
            ->map(function ($allocation) use ($primaryAllocationId) {
                $allocationAttributes = $allocation['attributes'] ?? $allocation;

                $allocationId = isset($allocationAttributes['id'])
                    ? (string) $allocationAttributes['id']
                    : (
                        isset($allocation['id'])
                            ? (string) $allocation['id']
                            : null
                    );

                return [
                    'id' => $allocationId,
                    'ip' => $allocationAttributes['ip'] ?? null,
                    'port' => isset($allocationAttributes['port'])
                        ? (int) $allocationAttributes['port']
                        : null,
                    'alias' => $allocationAttributes['alias'] ?? null,
                    'is_default' => $primaryAllocationId !== null
                        && $allocationId !== null
                        && $allocationId === $primaryAllocationId,
                ];
            })
            ->filter(
                fn (array $allocation) =>
                    filled($allocation['ip']) &&
                    filled($allocation['port'])
            )
            ->sortByDesc('is_default')
            ->values()
            ->all();

        $metadata = [
            'identifier' => $attributes['identifier'] ?? null,
            'external_id' => $attributes['external_id'] ?? null,
            'description' => $attributes['description'] ?? null,
            'suspended' => (bool) ($attributes['suspended'] ?? false),

            'user_id' => $userId,
            'node_id' => $nodeId,
            'nest_id' => $nestId,
            'egg_id' => $eggId,
            'primary_allocation_id' => $primaryAllocationId,

            'limits' => [
                'memory' => isset($attributes['limits']['memory'])
                    ? (int) $attributes['limits']['memory']
                    : 0,
                'swap' => isset($attributes['limits']['swap'])
                    ? (int) $attributes['limits']['swap']
                    : 0,
                'disk' => isset($attributes['limits']['disk'])
                    ? (int) $attributes['limits']['disk']
                    : 0,
                'io' => isset($attributes['limits']['io'])
                    ? (int) $attributes['limits']['io']
                    : 0,
                'cpu' => isset($attributes['limits']['cpu'])
                    ? (int) $attributes['limits']['cpu']
                    : 0,
                'threads' => $attributes['limits']['threads'] ?? null,
            ],

            'feature_limits' => $attributes['feature_limits'] ?? [],
            'container' => $attributes['container'] ?? [],
            'startup' => data_get(
                $attributes,
                'container.startup',
                $attributes['startup'] ?? null,
            ),
            'docker_image' => data_get($attributes, 'container.image'),
            'environment' => data_get(
                $attributes,
                'container.environment',
                [],
            ),

            'source_user' => $user,
            'source_node' => $node,
            'source_egg' => $egg,
            'raw' => $attributes,
        ];

        return new DiscoveredServer(
            id: (string) ($attributes['id'] ?? $resource['id'] ?? ''),
            uuid: $attributes['uuid'] ?? null,
            name: (string) ($attributes['name'] ?? 'Unnamed Server'),
            ownerEmail: $user['email'] ?? null,
            nodeName: $node['name']
                ?? ($nodeId !== null ? 'Node #' . $nodeId : null),
            eggName: $egg['name']
                ?? ($eggId !== null ? 'Egg #' . $eggId : null),
            allocations: $allocations,
            metadata: $metadata,
        );
    }

    private function relationshipData(
        array $resource,
        array $attributes,
        string $relationship,
    ): array {
        $candidates = [
            data_get(
                $resource,
                "relationships.{$relationship}.data",
            ),
            data_get(
                $attributes,
                "relationships.{$relationship}.data",
            ),
            data_get(
                $resource,
                "attributes.relationships.{$relationship}.data",
            ),
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                return $candidate;
            }
        }

        return [];
    }

    private function eggKey(string $nestId, string $eggId): string
    {
        return $nestId . ':' . $eggId;
    }
}