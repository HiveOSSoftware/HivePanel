<?php

namespace App\Enums;

enum BackupStatus: string
{
    case PENDING = 'pending';
    case CREATING = 'creating';
    case COMPLETED = 'completed';
    case RESTORING = 'restoring';
    case DELETING = 'deleting';
    case FAILED = 'failed';

    public function isProcessing(): bool
    {
        return match ($this) {
            self::PENDING,
            self::CREATING,
            self::RESTORING,
            self::DELETING => true,

            self::COMPLETED,
            self::FAILED => false,
        };
    }

    public function isAvailable(): bool
    {
        return $this === self::COMPLETED;
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::CREATING => 'Creating',
            self::COMPLETED => 'Completed',
            self::RESTORING => 'Restoring',
            self::DELETING => 'Deleting',
            self::FAILED => 'Failed',
        };
    }
}