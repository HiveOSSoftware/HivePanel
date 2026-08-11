<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Cell;
use App\Models\Node;
use App\Models\User;
use App\Services\Node\NodeClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Throwable;

class AdminDashboardController extends Controller
{
    public function __invoke(NodeClient $nodeClient)
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'nodes' => Node::count(),
                'active_nodes' => Node::where('is_active', true)->count(),
                'cells' => Cell::count(),
                'users' => User::count(),
                'audit_logs' => AuditLog::whereHas('user', fn ($query) => $query->where('is_admin', true))->count(),
            ],
            'recentLogs' => AuditLog::query()
                ->with(['user:id,name,email', 'cell:id,name'])
                ->whereHas('user', fn ($query) => $query->where('is_admin', true))
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'event' => $log->event,
                    'description' => $log->description,
                    'created_at' => $log->created_at?->toISOString(),
                    'user' => $log->user ? [
                        'name' => $log->user->name,
                        'email' => $log->user->email,
                    ] : null,
                    'cell' => $log->cell ? [
                        'id' => $log->cell->id,
                        'name' => $log->cell->name,
                    ] : null,
                ]),
            'versionStatus' => $this->versionStatus(),
            'workerVersions' => $this->workerVersions($nodeClient),
            'quickLinks' => [
                [
                    'label' => 'Get Help',
                    'description' => 'Join the Discord community.',
                    'url' => 'https://hivepanel.dev/r/discord',
                    'external' => true,
                ],
                [
                    'label' => 'Documentation',
                    'description' => 'Read the HivePanel docs.',
                    'url' => 'https://docs.hivepanel.dev',
                    'external' => true,
                ],
                [
                    'label' => 'GitHub',
                    'description' => 'View the source code.',
                    'url' => 'https://github.com/HiveOSSoftware/HivePanel',
                    'external' => true,
                ],
                [
                    'label' => 'Support Project',
                    'description' => 'Help fund development.',
                    'url' => 'https://github.com/sponsors/HiveOSSoftware',
                    'external' => true,
                ],
            ],
        ]);
    }

    private function versionStatus(): array
    {
        $current = config('hivepanel.version', config('app.version', '0.0.0'));

        $latest = Cache::remember('hivepanel.latest_version', now()->addHour(), function () {
            try {
                $response = Http::timeout(5)
                    ->acceptJson()
                    ->get('https://api.github.com/repos/HiveOSSoftware/HivePanel/releases/latest');

                if (! $response->successful()) {
                    return null;
                }

                return ltrim((string) $response->json('tag_name'), 'v');
            } catch (Throwable) {
                return null;
            }
        });

        return [
            'current' => $current,
            'latest' => $latest,
            'is_outdated' => $latest ? version_compare($current, $latest, '<') : false,
            'checked' => $latest !== null,
        ];
    }

    private function workerVersions(NodeClient $nodeClient): array
    {
        return Node::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (Node $node) use ($nodeClient) {
                return Cache::remember("hivepanel.worker_version.{$node->id}", now()->addSeconds(30), function () use ($node, $nodeClient) {
                    try {
                        $response = $nodeClient->client($node)->get('/version');

                        if ($response->status() === 404) {
                            return [
                                'id' => $node->id,
                                'name' => $node->name,
                                'version' => null,
                                'reachable' => true,
                                'version_available' => false,
                            ];
                        }

                        if (! $response->successful()) {
                            return [
                                'id' => $node->id,
                                'name' => $node->name,
                                'version' => null,
                                'reachable' => true,
                                'version_available' => false,
                            ];
                        }

                        $version = trim((string) $response->json('version'));

                        return [
                            'id' => $node->id,
                            'name' => $node->name,
                            'version' => $version !== '' ? $version : null,
                            'reachable' => true,
                            'version_available' => $version !== '',
                        ];
                    } catch (ConnectionException) {
                        return [
                            'id' => $node->id,
                            'name' => $node->name,
                            'version' => null,
                            'reachable' => false,
                            'version_available' => false,
                        ];
                    } catch (Throwable) {
                        return [
                            'id' => $node->id,
                            'name' => $node->name,
                            'version' => null,
                            'reachable' => false,
                            'version_available' => false,
                        ];
                    }
                });
            })
            ->values()
            ->all();
    }
}