<?php

namespace App\Services\Migrations\Data;

final class DiscoveredServer
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $uuid,
        public readonly string $name,
        public readonly ?string $ownerEmail,
        public readonly ?string $nodeName,
        public readonly ?string $eggName,
        public readonly array $allocations,
        public readonly array $metadata,
    ) {
    }

    public function toArray(): array
    {
        return [
            'source_server_id' => $this->id,
            'source_uuid' => $this->uuid,
            'name' => $this->name,
            'owner_email' => $this->ownerEmail,
            'source_node_name' => $this->nodeName,
            'source_egg_name' => $this->eggName,
            'source_allocations' => $this->allocations,
            'source_metadata' => $this->metadata,
        ];
    }
}