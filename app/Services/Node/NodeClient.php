<?php

namespace App\Services\Node;

use App\Models\Node;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class NodeClient
{
    public function client(Node $node): PendingRequest
    {
        return Http::baseUrl($node->baseUrl())
            ->withToken($node->api_token)
            ->timeout(10);
    }
}