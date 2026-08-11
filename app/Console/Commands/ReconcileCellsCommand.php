<?php

namespace App\Console\Commands;

use App\Enums\CellInstallStatus;
use App\Jobs\ReconcileCellJob;
use App\Models\Cell;
use Illuminate\Console\Command;

class ReconcileCellsCommand extends Command
{
    protected $signature = 'cells:reconcile';
    protected $description = 'Check installed Cells against their assigned Workers';

    public function handle(): int
    {
        $count = 0;

        Cell::query()
            ->where('install_status', CellInstallStatus::INSTALLED->value)
            ->whereNotNull('node_id')
            ->whereNotNull('daemon_id')
            ->orderBy('id')
            ->chunkById(100, function ($cells) use (&$count) {
                foreach ($cells as $cell) {
                    ReconcileCellJob::dispatch($cell->id);
                    $count++;
                }
            });

        $this->info("Queued {$count} Cell reconciliation job(s).");

        return self::SUCCESS;
    }
}