<?php

namespace App\Schedules\Templates;

class ScheduleTemplateRegistry
{
    public static function forComb(array|string|null $comb): array
    {
        $slug = match (true) {
            is_array($comb) => strtolower($comb['slug'] ?? $comb['name'] ?? ''),
            is_string($comb) => strtolower($comb),
            default => '',
        };

        return [
            ...GenericScheduleTemplates::templates(),
            ...match (true) {
                str_contains($slug, 'minecraft') => MinecraftScheduleTemplates::templates(),
                str_contains($slug, 'rust') => RustScheduleTemplates::templates(),
                str_contains($slug, 'fivem') => FiveMScheduleTemplates::templates(),
                default => [],
            },
        ];
    }
}