<?php

namespace App\Services\Sftp;

use App\Models\Cell;
use App\Models\User;

class SftpAccessService
{
    public function resolve(Cell $cell, User $user): ?array
    {
        if ($this->hasFullAccess($cell, $user)) {
            return $this->fullPermissions();
        }

        $subuser = $cell->subusers()
            ->whereKey($user->id)
            ->first();

        if (! $subuser) {
            return null;
        }

        $permissions = $subuser->pivot->permissions ?? [];

        if (is_string($permissions)) {
            $permissions = json_decode($permissions, true) ?: [];
        }

        if (! is_array($permissions)) {
            return null;
        }

        $resolved = [
            'read' => $this->hasAny($permissions, [
                'files.read',
                'files.view',
                'file.read',
                'file.view',
                'sftp.read',
            ]),

            'write' => $this->hasAny($permissions, [
                'files.write',
                'files.edit',
                'file.write',
                'file.edit',
                'sftp.write',
            ]),

            'create' => $this->hasAny($permissions, [
                'files.create',
                'files.write',
                'file.create',
                'file.write',
                'sftp.create',
                'sftp.write',
            ]),

            'rename' => $this->hasAny($permissions, [
                'files.rename',
                'files.write',
                'file.rename',
                'file.write',
                'sftp.rename',
                'sftp.write',
            ]),

            'delete' => $this->hasAny($permissions, [
                'files.delete',
                'file.delete',
                'sftp.delete',
            ]),
        ];

        /*
         * An SFTP account without read access is not useful because the
         * client cannot list or inspect the cell directory.
         */
        if (! $resolved['read']) {
            return null;
        }

        return $resolved;
    }

    public function canAccess(Cell $cell, User $user): bool
    {
        return $this->resolve($cell, $user) !== null;
    }

    private function hasFullAccess(Cell $cell, User $user): bool
    {
        return (string) $cell->owner_id === (string) $user->id
            || (bool) $user->is_admin;
    }

    private function fullPermissions(): array
    {
        return [
            'read' => true,
            'write' => true,
            'create' => true,
            'rename' => true,
            'delete' => true,
        ];
    }

    private function hasAny(array $permissions, array $required): bool
    {
        /*
         * Support either:
         *
         * ["files.read", "files.write"]
         *
         * or:
         *
         * {
         *     "files.read": true,
         *     "files.write": true
         * }
         */
        foreach ($required as $permission) {
            if (in_array($permission, $permissions, true)) {
                return true;
            }

            if (($permissions[$permission] ?? false) === true) {
                return true;
            }
        }

        return false;
    }
}