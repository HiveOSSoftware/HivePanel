<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cells', function (Blueprint $table) {
            $table->uuid('primary_allocation_id')->nullable()->after('node_id');
        });

        $cells = DB::table('cells')
            ->select([
                'id',
                'metadata',
            ])
            ->get();

        foreach ($cells as $cell) {
            $metadata = json_decode($cell->metadata ?? '{}', true) ?: [];

            $allocationId = data_get($metadata, 'allocation.id');

            if (! $allocationId) {
                continue;
            }

            $exists = DB::table('node_allocations')
                ->where('id', $allocationId)
                ->where('cell_id', $cell->id)
                ->exists();

            if (! $exists) {
                continue;
            }

            DB::table('cells')
                ->where('id', $cell->id)
                ->update([
                    'primary_allocation_id' => $allocationId,
                ]);
        }

        Schema::table('cells', function (Blueprint $table) {
            $table->foreign('primary_allocation_id')
                ->references('id')
                ->on('node_allocations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cells', function (Blueprint $table) {
            $table->dropForeign(['primary_allocation_id']);
            $table->dropColumn('primary_allocation_id');
        });
    }
};