<?php

namespace App\Services\Migrations\Sources;

class PterodactylForkSourceConnector extends PterodactylCompatibleSourceConnector
{
    protected function tolerateMissingEggs(): bool
    {
        return true;
    }
}