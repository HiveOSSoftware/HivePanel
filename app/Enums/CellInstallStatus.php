<?php

namespace App\Enums;

enum CellInstallStatus: string
{
    case PENDING = 'pending';
    case INSTALLING = 'installing';
    case INSTALLED = 'installed';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::INSTALLING => 'Installing',
            self::INSTALLED => 'Installed',
            self::FAILED => 'Installation Failed',
        };
    }
}