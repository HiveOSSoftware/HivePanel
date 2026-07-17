<?php

namespace App\Enums;

enum BackupMountStatus: string
{
    case MOUNTING = 'mounting';
    case MOUNTED = 'mounted';
    case UNMOUNTING = 'unmounting';
    case UNMOUNTED = 'unmounted';
    case FAILED = 'failed';

    public function isActive(): bool
    {
        return match ($this) {
            self::MOUNTING,
            self::MOUNTED,
            self::UNMOUNTING => true,

            self::UNMOUNTED,
            self::FAILED => false,
        };
    }

    public function isBrowsable(): bool
    {
        return $this === self::MOUNTED;
    }

    public function label(): string
    {
        return match ($this) {
            self::MOUNTING => 'Mounting',
            self::MOUNTED => 'Mounted',
            self::UNMOUNTING => 'Unmounting',
            self::UNMOUNTED => 'Unmounted',
            self::FAILED => 'Failed',
        };
    }
}