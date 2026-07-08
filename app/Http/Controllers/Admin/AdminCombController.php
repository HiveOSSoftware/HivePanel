<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comb;
use App\Services\RegistryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminCombController extends Controller
{
    public function index(RegistryService $registry)
    {
        return Inertia::render('Admin/Combs/Index', [
            'combs' => Comb::query()
                ->latest()
                ->get()
                ->map(fn (Comb $comb) => $this->combPayload($comb)),

            'registryCombs' => $registry->getCombs() ?? [],
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Combs/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'external_id' => ['required', 'string', 'max:255', 'unique:combs,external_id'],
            'name' => ['required', 'string', 'max:255'],
            'game' => ['required', 'string', 'max:255'],
            'manifest' => ['required', 'string'],
        ]);

        $json = json_decode($data['manifest'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return back()
                ->withErrors(['manifest' => 'The comb JSON is invalid.'])
                ->withInput();
        }

        Comb::create([
            'external_id' => $data['external_id'],
            'name' => $data['name'],
            'game' => $data['game'],
            'source' => 'manual',
            'data' => $json,
        ]);

        return redirect()->route('admin.combs.index');
    }

    public function show(Comb $comb)
    {
        return Inertia::render('Admin/Combs/Show', [
            'comb' => $this->combPayload($comb),
        ]);
    }

    public function importFromRegistry(string $id, RegistryService $registry)
    {
        $comb = $registry->getComb($id);

        abort_if(! $comb || ! isset($comb['id']), 404, 'Comb not found in registry.');

        $existing = Comb::where('external_id', $comb['id'])->first();

        if ($existing && in_array($existing->source, ['registry_modified', 'manual'], true)) {
            return back()->withErrors([
                'comb' => 'This comb has local changes. Delete it or import it as a copy instead.',
            ]);
        }

        Comb::updateOrCreate(
            ['external_id' => $comb['id']],
            [
                'name' => $comb['name'] ?? $comb['id'],
                'game' => $comb['game'] ?? 'unknown',
                'source' => 'registry',
                'data' => $comb,
            ]
        );

        return redirect()
            ->route('admin.combs.index')
            ->with('success', "Imported {$comb['name']}.");
    }

    public function destroy(Comb $comb)
    {
        $comb->delete();

        return redirect()->route('admin.combs.index');
    }

    private function combPayload(Comb $comb): array
    {
        return [
            'id' => $comb->id,
            'external_id' => $comb->external_id,
            'name' => $comb->name,
            'game' => $comb->game,
            'source' => $comb->source,
            'data' => $comb->data,
            'created_at' => $comb->created_at?->toISOString(),
            'updated_at' => $comb->updated_at?->toISOString(),
        ];
    }

    public function edit(Comb $comb)
    {
        return Inertia::render('Admin/Combs/Edit', [
            'comb' => $this->combPayload($comb),
        ]);
    }

    public function update(Request $request, Comb $comb)
    {
        $data = $request->validate([
            'external_id' => ['required', 'string', 'max:255', 'unique:combs,external_id,' . $comb->id],
            'name' => ['required', 'string', 'max:255'],
            'game' => ['required', 'string', 'max:255'],
            'manifest' => ['required', 'string'],
        ]);

        $json = json_decode($data['manifest'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return back()
                ->withErrors(['manifest' => 'The comb JSON is invalid.'])
                ->withInput();
        }

        $comb->update([
            'external_id' => $data['external_id'],
            'name' => $data['name'],
            'game' => $data['game'],
            'source' => $comb->source === 'registry' ? 'registry_modified' : $comb->source,
            'data' => $json,
        ]);

        return redirect()->route('admin.combs.show', $comb);
    }
}