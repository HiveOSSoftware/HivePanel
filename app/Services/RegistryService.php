<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegistryService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('hivepanel.registry_url', ''), '/');
    }

    public function getCombs(): array
    {
        return $this->getCachedJson('/api/v1/combs') ?? [];
    }

    public function getComb(string $id): ?array
    {
        return $this->getCachedJson("/api/v1/combs/{$id}");
    }

    public function getJars(): array
    {
        return $this->getCachedJson('/api/v1/jars') ?? [];
    }

    public function clearCache(): void
    {
        Cache::forget($this->cacheKey('/api/v1/combs'));
        Cache::forget($this->cacheKey('/api/v1/jars'));
    }

    private function getCachedJson(string $path): ?array
    {
        return Cache::remember(
            $this->cacheKey($path),
            now()->addMinutes(10),
            fn () => $this->getJson($path)
        );
    }

    private function getJson(string $path): ?array
    {
        if ($this->baseUrl === '') {
            return null;
        }

        $url = $this->baseUrl . $path;

        try {
            $response = Http::timeout(5)
                ->retry(2, 200)
                ->withoutVerifying() // TEMP FIX REMOVE FROM PROD
                ->acceptJson()
                ->get($url);

            if (! $response->successful()) {
                Log::warning('HiveRegistry request failed.', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->json();
        } catch (Throwable $e) {
            Log::warning('HiveRegistry unavailable.', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function cacheKey(string $path): string
    {
        return 'hive_registry:' . md5($this->baseUrl . $path);
    }
}