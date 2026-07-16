<?php

namespace App\Support;

class CellPermissions
{
    public const VIEW = 'cell.view';

    public const ACTIVITY_VIEW = 'activity.view';

    public const CONSOLE_VIEW = 'console.view';
    public const CONSOLE_SEND = 'console.send';
    public const CONSOLE_POWER = 'console.power';

    public const FILES_VIEW = 'files.view';
    public const FILES_READ = 'files.read';
    public const FILES_WRITE = 'files.write';
    public const FILES_UPLOAD = 'files.upload';
    public const FILES_DELETE = 'files.delete';
    public const FILES_RESTORE = 'files.restore';

    public const BACKUPS_VIEW = 'backups.view';
    public const BACKUPS_CREATE = 'backups.create';
    public const BACKUPS_RESTORE = 'backups.restore';
    public const BACKUPS_DELETE = 'backups.delete';

    public const SCHEDULES_VIEW = 'schedules.view';
    public const SCHEDULES_CREATE = 'schedules.create';
    public const SCHEDULES_UPDATE = 'schedules.update';
    public const SCHEDULES_DELETE = 'schedules.delete';

    public const DATABASES_VIEW = 'databases.view';
    public const DATABASES_CREATE = 'databases.create';
    public const DATABASES_DELETE = 'databases.delete';

    public const USERS_VIEW = 'users.view';
    public const USERS_INVITE = 'users.invite';
    public const USERS_UPDATE = 'users.update';
    public const USERS_REMOVE = 'users.remove';

    public const SETTINGS_VIEW = 'settings.view';
    public const SETTINGS_UPDATE = 'settings.update';
    public const STARTUP_UPDATE = 'startup.update';
    public const NETWORK_UPDATE = 'network.update';

    public const SFTP_RESET = 'sftp.reset';
    public const SFTP_REVOKE = 'sftp.revoke';

    public static function groups(): array
    {
        return [
            'General' => [
                self::VIEW => 'View server',
            ],
            'Console' => [
                self::CONSOLE_VIEW => 'View console',
                self::CONSOLE_SEND => 'Send commands',
                self::CONSOLE_POWER => 'Start, stop, restart server',
            ],
            'Files' => [
                self::FILES_VIEW => 'View file manager',
                self::FILES_READ => 'Read files',
                self::FILES_WRITE => 'Edit files',
                self::FILES_UPLOAD => 'Upload files',
                self::FILES_DELETE => 'Delete files',
                self::FILES_RESTORE => 'Restore deleted files',
                self::SFTP_RESET => 'Reset SFTP password',
                self::SFTP_REVOKE => 'Revoke SFTP access',
            ],
            'Backups' => [
                self::BACKUPS_VIEW => 'View backups',
                self::BACKUPS_CREATE => 'Create backups',
                self::BACKUPS_RESTORE => 'Restore backups',
                self::BACKUPS_DELETE => 'Delete backups',
            ],
            'Schedules' => [
                self::SCHEDULES_VIEW => 'View schedules',
                self::SCHEDULES_CREATE => 'Create schedules',
                self::SCHEDULES_UPDATE => 'Edit schedules',
                self::SCHEDULES_DELETE => 'Delete schedules',
            ],
            'Databases' => [
                self::DATABASES_VIEW => 'View databases',
                self::DATABASES_CREATE => 'Create databases',
                self::DATABASES_DELETE => 'Delete databases',
            ],
            'Sub Users' => [
                self::USERS_VIEW => 'View sub users',
                self::USERS_INVITE => 'Invite sub users',
                self::USERS_UPDATE => 'Edit sub user permissions',
                self::USERS_REMOVE => 'Remove sub users',
            ],
            'Settings' => [
                self::SETTINGS_VIEW => 'View settings',
                self::SETTINGS_UPDATE => 'Edit settings',
                self::STARTUP_UPDATE => 'Edit startup',
                self::NETWORK_UPDATE => 'Edit network',
            ],
        ];
    }

    public static function all(): array
    {
        return collect(self::groups())
            ->flatMap(fn ($group) => array_keys($group))
            ->values()
            ->all();
    }
}