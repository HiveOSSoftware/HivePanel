<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_migration_servers', function (Blueprint $table) {
            $table->string('owner_strategy', 50)
                ->default('existing')
                ->after('destination_owner_id');

            $table->json('owner_create_data')
                ->nullable()
                ->after('owner_strategy');

            $table->string('comb_strategy', 50)
                ->default('existing')
                ->after('destination_comb');

            $table->json('comb_create_data')
                ->nullable()
                ->after('comb_strategy');
        });
    }

    public function down(): void
    {
        Schema::table('platform_migration_servers', function (Blueprint $table) {
            $table->dropColumn([
                'owner_strategy',
                'owner_create_data',
                'comb_strategy',
                'comb_create_data',
            ]);
        });
    }
};