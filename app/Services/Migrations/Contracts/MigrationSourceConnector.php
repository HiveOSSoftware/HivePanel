<?php

namespace App\Services\Migrations\Contracts;

use App\Services\Migrations\Data\DiscoveredServer;

interface MigrationSourceConnector
{
    public function testConnection(): void;

    /**
     * @return array<int, DiscoveredServer>
     */
    public function discoverServers(): array;
}