<?php

namespace App\Http\Middleware;

use App\Models\Node;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Crypt;

class AuthenticateWorker
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! filled($token)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $nodeId = $request->header('X-Hive-Node');

        if (! filled($nodeId)) {
            return response()->json([
                'message' => 'Missing node identity.',
            ], 401);
        }

        $node = Node::query()
            ->whereKey($nodeId)
            ->first();

        if (! $node || ! filled($node->api_token)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! hash_equals(
            (string) $node->api_token,
            $token,
        )) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $request->attributes->set('worker_node', $node);

        return $next($request);
    }
}