<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_migration_servers', function (Blueprint $table) {
            $table->boolean('selected')
                ->default(true)
                ->after('source_allocations');

            $table->string('allocation_strategy', 50)
                ->default('preserve')
                ->after('destination_comb');
        });
    }

    public function down(): void
    {
        Schema::table('platform_migration_servers', function (Blueprint $table) {
            $table->dropColumn([
                'selected',
                'allocation_strategy',
            ]);
        });
    }
};