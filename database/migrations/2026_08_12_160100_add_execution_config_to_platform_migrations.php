<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_migrations', function (Blueprint $table) {
            $table->longText('execution_config')
                ->nullable()
                ->after('source_config');
        });
    }

    public function down(): void
    {
        Schema::table('platform_migrations', function (Blueprint $table) {
            $table->dropColumn('execution_config');
        });
    }
};