<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\Cell;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => User::query()
                ->withCount('cells')
                ->latest()
                ->get()
                ->map(fn (User $user) => $this->userPayload($user)),
        ]);
    }

    public function show(User $user)
    {
        $user->loadCount('cells');

        return Inertia::render('Admin/Users/Show', [
            'user' => $this->userPayload($user),
            'cells' => Cell::query()
                ->where('owner_id', $user->id)
                ->with(['node:id,name,location', 'allocation:id,cell_id,ip,port,alias'])
                ->latest()
                ->get()
                ->map(fn (Cell $cell) => [
                    'id' => $cell->getRouteKey(),
                    'name' => $cell->name,
                    'comb' => $cell->comb,
                    'daemon_id' => $cell->daemon_id,
                    'node' => $cell->node,
                    'allocation' => $cell->allocation,
                    'created_at' => $cell->created_at?->toISOString(),
                ]),
        ]);
    }

    public function edit(User $user)
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => $this->userPayload($user),
        ]);
    }

    public function update(Request $request, User $user, AuditLogger $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        $user->update($data);

        $audit->log(
            AuditEvent::USER_UPDATED,
            $user,
            "User \"{$user->email}\" was updated.",
            ['user_id' => $user->id]
        );

        return redirect()->route('admin.users.show', $user);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->getRouteKey(),
            'database_id' => $user->id,
            'is_admin' => $user->is_admin,
            'name' => $user->name,
            'email' => $user->email,
            'cells_count' => $user->cells_count ?? null,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }
}