<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Backup mount lifetime
    |--------------------------------------------------------------------------
    |
    | Mounted backups will automatically become eligible for cleanup after
    | this number of minutes.
    |
    */

    'mount_lifetime_minutes' => (int) env(
        'BACKUP_MOUNT_LIFETIME_MINUTES',
        60,
    ),

    /*
    |--------------------------------------------------------------------------
    | Maximum mounts per cell
    |--------------------------------------------------------------------------
    */

    'maximum_active_mounts_per_cell' => (int) env(
        'BACKUP_MAXIMUM_ACTIVE_MOUNTS',
        1,
    ),
];