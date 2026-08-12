<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_migration_servers', function (Blueprint $table) {
            $table->boolean('transfer_owner')
                ->default(true)
                ->after('owner_create_data');

            $table->json('database_plan')
                ->nullable()
                ->after('execution_plan');
        });
    }

    public function down(): void
    {
        Schema::table('platform_migration_servers', function (Blueprint $table) {
            $table->dropColumn([
                'transfer_owner',
                'database_plan',
            ]);
        });
    }
};