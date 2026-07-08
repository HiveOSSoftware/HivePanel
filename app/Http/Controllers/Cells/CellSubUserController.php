<?php

namespace App\Http\Controllers\Cells;

use App\Models\User;
use App\Support\CellPermissions;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CellSubUserController extends CellBaseController
{
    public function index(string $id)
    {
        $cell = $this->panelCellOrFail($id);

        abort_unless($cell->userCan(auth()->user(), CellPermissions::USERS_VIEW), 403);

        return Inertia::render('Cells/Users/Index', [
            'cell' => $cell->load('owner:id,name,email'),

            'users' => $cell->subUsers()
                ->with('user:id,name,email')
                ->latest()
                ->get()
                ->map(fn ($subUser) => [
                    'id' => $subUser->id,
                    'permissions' => $subUser->permissions ?? [],
                    'accepted_at' => $subUser->accepted_at,
                    'user' => [
                        'id' => $subUser->user->id,
                        'name' => $subUser->user->name,
                        'email' => $subUser->user->email,
                    ],
                ]),
        ]);
    }

    public function create(string $id)
    {
        $cell = $this->panelCellOrFail($id);

        abort_unless($cell->userCan(auth()->user(), CellPermissions::USERS_INVITE), 403);

        return Inertia::render('Cells/Users/Create', [
            'cell' => $cell->load('owner:id,name,email'),
            'permissionGroups' => CellPermissions::groups(),
        ]);
    }

    public function store(string $id, Request $request)
    {
        $cell = $this->panelCellOrFail($id);

        abort_unless($cell->userCan(auth()->user(), CellPermissions::USERS_INVITE), 403);

        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'in:' . implode(',', CellPermissions::all())],
        ]);

        $user = User::where('email', $data['email'])->firstOrFail();

        abort_if((string) $user->id === (string) $cell->owner_id, 422, 'The owner already has full access.');

        $cell->subUsers()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'permissions' => $data['permissions'],
                'accepted_at' => now(),
            ]
        );

        return redirect()->route('cells.users.index', $cell->id);
    }

    public function edit(string $id, User $user)
    {
        $cell = $this->panelCellOrFail($id);

        abort_unless($cell->userCan(auth()->user(), CellPermissions::USERS_UPDATE), 403);

        $subUser = $cell->subUsers()
            ->where('user_id', $user->id)
            ->with('user:id,name,email')
            ->firstOrFail();

        return Inertia::render('Cells/Users/Edit', [
            'cell' => $cell->load('owner:id,name,email'),
            'permissionGroups' => CellPermissions::groups(),
            'subUser' => [
                'id' => $subUser->id,
                'permissions' => $subUser->permissions ?? [],
                'accepted_at' => $subUser->accepted_at,
                'user' => [
                    'id' => $subUser->user->id,
                    'name' => $subUser->user->name,
                    'email' => $subUser->user->email,
                ],
            ],
        ]);
    }

    public function update(string $id, User $user, Request $request)
    {
        $cell = $this->panelCellOrFail($id);

        abort_unless($cell->userCan(auth()->user(), CellPermissions::USERS_UPDATE), 403);

        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'in:' . implode(',', CellPermissions::all())],
        ]);

        $cell->subUsers()
            ->where('user_id', $user->id)
            ->firstOrFail()
            ->update([
                'permissions' => $data['permissions'],
            ]);

        return redirect()->route('cells.users.index', $cell->id);
    }

    public function destroy(string $id, User $user)
    {
        $cell = $this->panelCellOrFail($id);

        abort_unless($cell->userCan(auth()->user(), CellPermissions::USERS_REMOVE), 403);

        $cell->subUsers()
            ->where('user_id', $user->id)
            ->firstOrFail()
            ->delete();

        return back();
    }
}