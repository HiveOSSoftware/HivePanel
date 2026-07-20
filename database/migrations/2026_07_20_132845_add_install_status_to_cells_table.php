<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cells', function (Blueprint $table) {
            $table->string('install_status')
                ->default('pending');

            $table->text('install_failure_reason')
                ->nullable();

            $table->timestamp('installed_at')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cells', function (Blueprint $table) {
            $table->dropColumn([
                'install_status',
                'install_failure_reason',
                'installed_at',
            ]);
        });
    }
};