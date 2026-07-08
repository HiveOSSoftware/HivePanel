<?php

namespace App\Http\Controllers;

use App\Models\Cell;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $cells = Cell::visibleTo($request->user())
            ->with('node')
            ->orderBy('name')
            ->get();

        return Inertia::render('Dashboard', [
            'cells' => $cells,
        ]);
    }
}