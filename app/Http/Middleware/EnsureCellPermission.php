<?php

namespace App\Http\Middleware;

use App\Models\Cell;
use Closure;
use Illuminate\Http\Request;

class EnsureCellPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $routeCell = $request->route('cell') ?? $request->route('id');

        $cell = $routeCell instanceof Cell
            ? $routeCell
            : Cell::query()
                ->where('id', $routeCell)
                // ->orWhere('uuid', $routeCell)
                ->orWhere('daemon_id', $routeCell)
                ->first();

        if (! $cell) {
            abort(404);
        }

        if (! $request->user() || ! $cell->userCan($request->user(), $permission)) {
            abort(403);
        }

        $request->attributes->set('panel_cell', $cell);

        return $next($request);
    }
}