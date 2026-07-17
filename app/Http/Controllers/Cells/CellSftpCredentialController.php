<?php

namespace App\Http\Controllers\Cells;

use App\Models\Cell;
use App\Models\SftpCredential;
use App\Models\User;
use App\Services\Sftp\SftpAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CellSftpCredentialController extends CellBaseController
{
    public function reset(
        Request $request,
        string $id,
        SftpAccessService $access,
    ): JsonResponse {
        $cell = $this->panelCellOrFail($id);
        $user = $request->user();

        abort_unless(
            $access->canAccess($cell, $user),
            403,
            'You do not have permission to use SFTP for this cell.',
        );

        abort_unless(
            (bool) $cell->node?->sftp_enabled,
            422,
            'SFTP is disabled on this node.',
        );

        $plainPassword = 'hp_sftp_' . Str::random(40);

        $credential = SftpCredential::updateOrCreate(
            [
                'user_id' => $user->id,
                'cell_id' => $cell->id,
            ],
            [
                'username' => $this->username($cell, $user),
                'password_hash' => Hash::make($plainPassword),
                'revoked_at' => null,
                'last_used_at' => null,
            ],
        );

        return response()->json([
            'message' => 'SFTP password generated.',
            'username' => $credential->username,
            'password' => $plainPassword,
            'has_password' => true,
        ]);
    }

    public function revoke(
        Request $request,
        string $id,
        SftpAccessService $access,
    ): JsonResponse {
        $cell = $this->panelCellOrFail($id);
        $user = $request->user();

        abort_unless(
            $access->canAccess($cell, $user),
            403,
            'You do not have permission to manage SFTP for this cell.',
        );

        $credential = SftpCredential::query()
            ->where('user_id', $user->id)
            ->where('cell_id', $cell->id)
            ->first();

        if (! $credential || $credential->revoked_at !== null) {
            return response()->json([
                'message' => 'SFTP access is already revoked.',
                'has_password' => false,
            ]);
        }

        $credential->forceFill([
            'revoked_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'SFTP access revoked.',
            'has_password' => false,
        ]);
    }

    private function username(Cell $cell, User $user): string
    {
        $accountUsername = filled($user->username)
            ? $user->username
            : Str::slug($user->name, '');

        $accountUsername = strtolower((string) $accountUsername);

        $accountUsername = preg_replace(
            '/[^a-z0-9_-]/',
            '',
            $accountUsername,
        ) ?? '';

        $accountUsername = substr($accountUsername, 0, 32);

        if ($accountUsername === '') {
            $accountUsername = 'user';
        }

        $cellSuffix = substr(
            str_replace('-', '', (string) $cell->id),
            0,
            8,
        );

        return "{$accountUsername}.{$cellSuffix}";
    }
}