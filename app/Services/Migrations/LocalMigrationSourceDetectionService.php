<?php

namespace App\Services\Migrations;

use App\Models\Node;
use App\Models\PlatformMigration;
use App\Services\Node\NodeClient;
use RuntimeException;

class LocalMigrationSourceDetectionService
{
    public function __construct(
        private readonly NodeClient $nodes,
    ) {
    }

    public function detect(
        PlatformMigration $migration,
        string $sourceNode,
        string $destinationNodeId,
        ?string $configuredPathTemplate = null,
    ): array {
        $node = Node::query()->find($destinationNodeId);

        if (! $node) {
            throw new RuntimeException(
                "Destination HivePanel Node is missing for source node {$sourceNode}."
            );
        }

        $uuids = $migration->servers()
            ->where('selected', true)
            ->where('source_node_name', $sourceNode)
            ->pluck('source_uuid')
            ->filter()
            ->map(
                fn ($uuid) =>
                    strtolower(
                        trim(
                            (string) $uuid
                        )
                    )
            )
            ->unique()
            ->values()
            ->all();

        if ($uuids === []) {
            throw new RuntimeException(
                "No selected source server UUIDs are available for {$sourceNode}."
            );
        }

        $configuredPath = $this->rootFromTemplate(
            $configuredPathTemplate
        );

        $response = $this->nodes
            ->client($node)
            ->post(
                '/migration/source-detect',
                [
                    'uuids' => $uuids,
                    'configured_path' => $configuredPath,
                ]
            )
            ->throw()
            ->json();

        if (! is_array($response)) {
            throw new RuntimeException(
                'The Worker returned an invalid local source detection response.'
            );
        }

        return $response;
    }

    private function rootFromTemplate(
        ?string $template,
    ): string {
        $template = trim(
            (string) $template
        );

        if ($template === '') {
            return '';
        }

        $root = str_replace(
            [
                '/{uuid}',
                '\\{uuid}',
                '{uuid}',
            ],
            '',
            $template,
        );

        return rtrim(
            trim($root),
            '/\\'
        );
    }
}