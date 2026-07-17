<?php

namespace App\Http\Controllers\Api\Workers;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\SftpCredential;
use App\Services\Sftp\SftpAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SftpAuthController extends Controller
{
    public function __invoke(
        Request $request,
        SftpAccessService $access,
    ): JsonResponse {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:500'],
        ]);

        /** @var Node|null $node */
        $node = $request->attributes->get('worker_node');

        if (
            ! $node ||
            ! $node->is_active ||
            $node->maintenance_mode ||
            ! $node->sftp_enabled
        ) {
            return $this->denied();
        }

        $credential = SftpCredential::query()
            ->with([
                'cell.node',
                'user',
            ])
            ->where('username', $data['username'])
            ->first();

        if (
            ! $credential ||
            $credential->revoked_at !== null ||
            ! $credential->cell ||
            ! $credential->user
        ) {
            return $this->denied();
        }

        $cell = $credential->cell;
        $user = $credential->user;

        if ((string) $cell->node_id !== (string) $node->id) {
            return $this->denied();
        }

        if (! filled($cell->daemon_id)) {
            return $this->denied();
        }

        if (! Hash::check(
            $data['password'],
            $credential->password_hash,
        )) {
            return $this->denied();
        }

        /*
         * Resolve current access at login time. This means removing a
         * subuser or changing their permissions immediately changes SFTP.
         */
        $permissions = $access->resolve($cell, $user);

        if ($permissions === null) {
            return $this->denied();
        }

        $credential->forceFill([
            'last_used_at' => now(),
        ])->save();

        return response()->json([
            'allowed' => true,
            'credential_id' => $credential->id,
            'user_id' => $credential->user_id,
            'cell_id' => $credential->cell_id,
            'daemon_id' => $cell->daemon_id,
            'permissions' => $permissions,
        ]);
    }

    private function denied(): JsonResponse
    {
        return response()->json([
            'allowed' => false,
            'message' => 'Invalid SFTP credentials.',
        ], 401);
    }
}