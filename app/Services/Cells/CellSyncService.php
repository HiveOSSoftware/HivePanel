<?php

namespace App\Services\Cells;

use App\Enums\CellInstallStatus;
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
            'allocations',
        ]);

        $this->validateCell($cell);

        $worker = $this->cells->cellForSync($cell);

        if (! $worker['reachable']) {
            return $this->storeInspection($cell, [
                'status' => 'unreachable',
                'synced' => false,
                'repairable' => false,
                'message' => 'The node Worker is currently unreachable.',
                'differences' => [],
            ]);
        }

        if (! $worker['exists']) {
            return $this->storeInspection($cell, [
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
            ]);
        }

        $expected = $this->expectedDefinition($cell);
        $actual = $this->workerDefinition($worker['cell'] ?? []);

        $differences = $this->differences($expected, $actual);

        return $this->storeInspection($cell, [
            'status' => count($differences) === 0 ? 'synced' : 'out_of_sync',
            'synced' => count($differences) === 0,
            'repairable' => count($differences) > 0,
            'message' => count($differences) === 0
                ? 'The Worker definition matches HivePanel.'
                : 'The Worker definition differs from HivePanel.',
            'differences' => $differences,
            'expected' => $expected,
            'actual' => $actual,
        ]);
    }

    public function repair(Cell $cell): array
    {
        $inspection = $this->inspect($cell);

        if ($inspection['status'] === 'unreachable') {
            throw new RuntimeException('The node Worker is currently unreachable.');
        }

        if ($inspection['status'] === 'missing') {
            throw new RuntimeException('The Worker cell is missing and cannot currently be recreated automatically.');
        }

        if ($inspection['synced']) {
            return $inspection;
        }

        $this->cells->updateCellDefinition($cell);

        $result = $this->inspect($cell->fresh([
            'node',
            'allocation',
            'allocations',
        ]));

        if (! $result['synced']) {
            throw new RuntimeException('The Worker accepted the update but the cell definition is still out of sync.');
        }

        return $result;
    }

    public function recreateMissing(Cell $cell): array
    {
        $inspection = $this->inspect($cell);

        if ($inspection['status'] !== 'missing') {
            throw new RuntimeException('This Cell is not missing from the Worker.');
        }

        if (! $cell->node) {
            throw new RuntimeException('This Cell is not assigned to a node.');
        }

        if (! $cell->daemon_id) {
            throw new RuntimeException('This Cell does not have a daemon ID.');
        }

        if (! $cell->allocation) {
            throw new RuntimeException('This Cell does not have an allocation.');
        }

        $created = $this->cells->recreateMissingCell($cell);

        if (($created['id'] ?? null) !== $cell->daemon_id) {
            throw new RuntimeException('The Worker recreated the Cell with an unexpected ID.');
        }

        $result = $this->inspect($cell->fresh([
            'node',
            'allocation',
            'allocations',
        ]));

        if (! $result['synced']) {
            throw new RuntimeException('The Worker Cell was recreated but is still out of sync.');
        }

        $cell->forceFill([
            'worker_recovery_required' => true,
            'worker_recreated_at' => now(),
            'install_status' => CellInstallStatus::PENDING,
            'install_failure_reason' => null,
            'installed_at' => null,
        ])->save();

        return [
            ...$result,
            'recovery_required' => true,
            'message' => 'Worker Cell recreated successfully. Reinstallation is required to complete recovery.',
        ];
    }

    private function storeInspection(Cell $cell, array $inspection): array
    {
        $cell->forceFill([
            'worker_sync_status' => $inspection['status'],
            'worker_sync_message' => $inspection['message'] ?? null,
            'worker_sync_differences' => $inspection['differences'] ?? [],
            'worker_sync_checked_at' => now(),
        ])->save();

        return $inspection;
    }

    private function validateCell(Cell $cell): void
    {
        if (! $cell->node) {
            throw new RuntimeException('This cell is not assigned to a node.');
        }

        if (! $cell->daemon_id) {
            throw new RuntimeException('This cell does not have a daemon ID.');
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
            'additional_allocations' => $this->normaliseAllocations($payload['additional_allocations'] ?? []),
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
            'additional_allocations' => $this->normaliseAllocations($worker['additional_allocations'] ?? []),
            'limits' => $worker['limits'] ?? [],
        ]);
    }

    private function normaliseAllocations(array $allocations): array
    {
        $allocations = array_map(function ($allocation) {
            return [
                'ip' => (string) ($allocation['ip'] ?? ''),
                'port' => (int) ($allocation['port'] ?? 0),
            ];
        }, $allocations);

        usort($allocations, function (array $a, array $b) {
            $ipComparison = strcmp($a['ip'], $b['ip']);

            if ($ipComparison !== 0) {
                return $ipComparison;
            }

            return $a['port'] <=> $b['port'];
        });

        return array_values($allocations);
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