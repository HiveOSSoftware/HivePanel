<?php

namespace App\Enums;

enum AuditEvent: string
{
    case SERVER_CREATED = 'server.created';
    case SERVER_DELETED = 'server.deleted';
    case SERVER_STARTED = 'server.started';
    case SERVER_STOPPED = 'server.stopped';
    case SERVER_RESTARTED = 'server.restarted';
    case SERVER_KILLED = 'server.killed';
    case SERVER_REINSTALLED = 'server.reinstalled';
    case SERVER_TRANSFERRED = 'server.transferred';

    case CONSOLE_COMMAND = 'console.command';

    case FILE_CREATED = 'file.created';
    case FILE_EDITED = 'file.edited';
    case FILE_DELETED = 'file.deleted';
    case FILE_RESTORED = 'file.restored';
    case FILE_UPLOADED = 'file.uploaded';
    case FILE_DOWNLOADED = 'file.downloaded';
    case FOLDER_CREATED = 'folder.created';

    case BACKUP_CREATED = 'backup.created';
    case BACKUP_DELETED = 'backup.deleted';
    case BACKUP_RESTORED = 'backup.restored';
    case BACKUP_DOWNLOADED = 'backup.downloaded';

    case SCHEDULE_CREATED = 'schedule.created';
    case SCHEDULE_UPDATED = 'schedule.updated';
    case SCHEDULE_DELETED = 'schedule.deleted';
    case SCHEDULE_EXECUTED = 'schedule.executed';
    case SCHEDULE_ENABLED = 'schedule.enabled';
    case SCHEDULE_DISABLED = 'schedule.disabled';

    case SETTINGS_UPDATED = 'settings.updated';
    case CONFIG_UPDATED = 'config.updated';

    case PLAYER_KICKED = 'player.kicked';
    case PLAYER_BANNED = 'player.banned';
    case PLAYER_OPPED = 'player.opped';
    case PLAYER_DEOPPED = 'player.deopped';

    case IMPORT_STARTED = 'import.started';
    case IMPORT_TESTED = 'import.tested';

    case SUBUSER_CREATED = 'subuser.created';
    case SUBUSER_DELETED = 'subuser.deleted';
    case SUBUSER_PERMISSIONS_UPDATED = 'subuser.permissions.updated';

    case NODE_CREATED = 'node.created';
    case NODE_UPDATED = 'node.updated';
    case NODE_DELETED = 'node.deleted';
}