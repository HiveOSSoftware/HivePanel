<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

class AppSettings
{
    public static function get(string $key, array $default = []): array
    {
        return Cache::rememberForever("settings:{$key}", function () use ($key, $default) {
            return AppSetting::where('key', $key)->first()?->value ?? $default;
        });
    }

    public static function clear(string $key): void
    {
        Cache::forget("settings:{$key}");
    }

    public static function general(): array
    {
        return self::get('general', [
            'company_name' => 'HivePanel',
            'company_logo' => null,
            'require_2fa' => 'not_required',
            'default_language' => 'en',
        ]);
    }

    public static function name(): string
    {
        return self::general()['company_name'] ?? 'HivePanel';
    }

    public static function logo(): ?string
    {
        return self::general()['company_logo'] ?? null;
    }
}