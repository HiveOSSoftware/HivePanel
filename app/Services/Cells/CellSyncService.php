<?php

namespace App\Services\Cells;

use App\Models\Cell;
use App\Services\Node\CellNodeClient;
use RuntimeException;

class CellSyncService
{
    public function __construct(private readonly CellNodeClient $cells) {
    }

    public function inspect(Cell $cell): array
    {
        $cell->loadMissing([
            'node',
            'allocation',
        ]);

        $this->validateCell($cell);

        $worker = $this->cells->cellForSync($cell);

        if (! $worker['reachable']) {
            return [
                'status' => 'unreachable',
                'synced' => false,
                'repairable' => false,
                'message' => 'The node Worker is currently unreachable.',
                'differences' => [],
            ];
        }

        if (! $worker['exists']) {
            return [
                'status' => 'missing',
                'synced' => false,
                'repairable' => false,
                'message' => 'This cell exists in HivePanel but is missing from the Worker.',
                'differences' => [
                    [
                        'field' => 'worker_cell',
                        'panel' => $cell->daemon_id,
                        'worker' => null,
                    ],
                ],
            ];
        }

        $expected = $this->expectedDefinition($cell);
        $actual = $this->workerDefinition($worker['cell'] ?? []);

        $differences = $this->differences(
            $expected,
            $actual,
        );

        return [
            'status' => count($differences) === 0
                ? 'synced'
                : 'out_of_sync',

            'synced' => count($differences) === 0,
            'repairable' => count($differences) > 0,

            'message' => count($differences) === 0
                ? 'The Worker definition matches HivePanel.'
                : 'The Worker definition differs from HivePanel.',

            'differences' => $differences,

            'expected' => $expected,
            'actual' => $actual,
        ];
    }

    public function repair(Cell $cell): array
    {
        $inspection = $this->inspect($cell);

        if ($inspection['status'] === 'unreachable') {
            throw new RuntimeException(
                'The node Worker is currently unreachable.',
            );
        }

        if ($inspection['status'] === 'missing') {
            throw new RuntimeException(
                'The Worker cell is missing and cannot currently be recreated automatically.',
            );
        }

        if ($inspection['synced']) {
            return $inspection;
        }

        $this->cells->updateCellDefinition($cell);

        $result = $this->inspect(
            $cell->fresh([
                'node',
                'allocation',
            ]),
        );

        if (! $result['synced']) {
            throw new RuntimeException(
                'The Worker accepted the update but the cell definition is still out of sync.',
            );
        }

        return $result;
    }

    private function validateCell(Cell $cell): void
    {
        if (! $cell->node) {
            throw new RuntimeException(
                'This cell is not assigned to a node.',
            );
        }

        if (! $cell->daemon_id) {
            throw new RuntimeException(
                'This cell does not have a daemon ID.',
            );
        }
    }

    private function expectedDefinition(Cell $cell): array
    {
        $payload = $this->cells->definitionPayload($cell);

        return $this->normalise([
            'name' => $payload['name'] ?? null,
            'comb' => $payload['comb'] ?? null,
            'comb_data' => $payload['comb_data'] ?? [],
            'variables' => $payload['variables'] ?? [],
            'allocation' => $payload['allocation'] ?? null,
            'limits' => $payload['limits'] ?? [],
        ]);
    }

    private function workerDefinition(array $worker): array
    {
        return $this->normalise([
            'name' => $worker['name'] ?? null,
            'comb' => $worker['comb'] ?? null,
            'comb_data' => $worker['comb_data'] ?? [],
            'variables' => $worker['variables'] ?? [],
            'allocation' => $worker['allocation'] ?? null,
            'limits' => $worker['limits'] ?? [],
        ]);
    }

    private function differences(array $expected, array $actual): array
    {
        $differences = [];

        foreach ($expected as $field => $panelValue) {
            $workerValue = $actual[$field] ?? null;

            if ($panelValue === $workerValue) {
                continue;
            }

            $differences[] = [
                'field' => $field,
                'panel' => $panelValue,
                'worker' => $workerValue,
            ];
        }

        return $differences;
    }

    private function normalise(array $value): array
    {
        return $this->normaliseValue($value);
    }

    private function normaliseValue(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(function ($item) {
                if (is_array($item)) {
                    return $this->normaliseValue($item);
                }

                return $item;
            }, $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->normaliseValue($item);
            }
        }

        return $value;
    }
}