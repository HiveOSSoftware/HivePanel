<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comb;
use App\Services\RegistryService;

class AdminCombImportController extends Controller
{
    public function import($id, RegistryService $registry)
    {
        $comb = $registry->getComb($id);

        if (!$comb) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return Comb::updateOrCreate(
            ['external_id' => $comb['id']],
            [
                'name' => $comb['name'],
                'game' => $comb['game'],
                'data' => json_encode($comb),
            ]
        );
    }

    public function sync(RegistryService $registry)
    {
        $combs = $registry->getCombs();

        foreach ($combs as $comb) {
            Comb::updateOrCreate(
                ['external_id' => $comb['id']],
                [
                    'name' => $comb['name'],
                    'game' => $comb['game'],
                    'data' => json_encode($comb),
                ]
            );
        }

        return response()->json([
            'imported' => count($combs)
        ]);
    }
}