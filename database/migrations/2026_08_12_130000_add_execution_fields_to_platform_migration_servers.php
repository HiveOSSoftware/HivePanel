<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_migration_servers', function (Blueprint $table) {
            $table->json('execution_plan')
                ->nullable()
                ->after('allocation_strategy');

            $table->timestamp('started_at')
                ->nullable()
                ->after('error');

            $table->timestamp('completed_at')
                ->nullable()
                ->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('platform_migration_servers', function (Blueprint $table) {
            $table->dropColumn([
                'execution_plan',
                'started_at',
                'completed_at',
            ]);
        });
    }
};