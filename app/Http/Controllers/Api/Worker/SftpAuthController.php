<?php

namespace App\Http\Controllers\Api\Worker;

use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\SftpCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SftpAuthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:500'],
        ]);

        /** @var Node|null $node */
        $node = $request->attributes->get('worker_node');

        if (! $node) {
            return $this->denied();
        }

        if (! $node->is_active || $node->maintenance_mode) {
            return $this->denied();
        }

        if (! $node->sftp_enabled) {
            return $this->denied();
        }

        $credential = SftpCredential::query()
            ->with([
                'cell:id,node_id,daemon_id',
                'user:id',
            ])
            ->where('username', $data['username'])
            ->first();

        if (! $credential) {
            return $this->denied();
        }

        if ($credential->revoked_at !== null) {
            return $this->denied();
        }

        if (! $credential->cell) {
            return $this->denied();
        }

        if ((string) $credential->cell->node_id !== (string) $node->id) {
            return $this->denied();
        }

        if (! filled($credential->cell->daemon_id)) {
            return $this->denied();
        }

        if (! Hash::check($data['password'], $credential->password_hash)) {
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
            'daemon_id' => $credential->cell->daemon_id,

            'root' => $this->cellRoot($credential->cell->daemon_id),

            'permissions' => [
                'read' => true,
                'write' => true,
                'create' => true,
                'rename' => true,
                'delete' => true,
            ],
        ]);
    }

    private function cellRoot(string $daemonId): string
    {
        return "/var/lib/hivepanel/cells/{$daemonId}";
    }

    private function denied(): JsonResponse
    {
        return response()->json([
            'allowed' => false,
            'message' => 'Invalid SFTP credentials.',
        ], 401);
    }
}