<?php

namespace App\Http\Controllers\Cells;

use App\Models\Cell;
use App\Models\SftpCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CellSftpCredentialController extends CellBaseController
{
    public function reset(Request $request, string $id)
    {
        $cell = $this->panelCellOrFail($id);
        $user = $request->user();

        abort_unless($cell->owner_id === $user->id || $user->is_admin, 403);

        $plainPassword = 'hp_sftp_' . Str::random(40);

        $credential = SftpCredential::updateOrCreate(
            [
                'user_id' => $user->id,
                'cell_id' => $cell->id,
            ],
            [
                'username' => $this->username($cell, $user->id),
                'password_hash' => Hash::make($plainPassword),
                'revoked_at' => null,
            ],
        );

        return back()->with([
            'success' => 'SFTP password generated.',
            'sftp_password' => $plainPassword,
            'sftp_username' => $credential->username,
        ]);
    }

    public function revoke(Request $request, string $id)
    {
        $cell = $this->panelCellOrFail($id);
        $user = $request->user();

        abort_unless($cell->owner_id === $user->id || $user->is_admin, 403);

        SftpCredential::query()
            ->where('user_id', $user->id)
            ->where('cell_id', $cell->id)
            ->update([
                'revoked_at' => now(),
            ]);

        return back()->with('success', 'SFTP access revoked.');
    }

    private function username(Cell $cell, string|int $userId): string
    {
        return "{$cell->id}.{$userId}";
    }
}