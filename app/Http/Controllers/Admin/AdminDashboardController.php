<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Cell;
use App\Models\Node;
use App\Models\User;
use Inertia\Inertia;

class AdminDashboardController extends Controller
{
    public function __invoke()
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'nodes' => Node::count(),
                'active_nodes' => Node::where('is_active', true)->count(),
                'cells' => Cell::count(),
                'users' => User::count(),
                'audit_logs' => AuditLog::count(),
            ],
            'recentLogs' => AuditLog::query()
                ->with(['user:id,name,email', 'cell:id,name'])
                ->latest()
                ->limit(8)
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
        ]);
    }
}