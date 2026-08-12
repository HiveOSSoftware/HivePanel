<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cells', function (Blueprint $table) {
            $table->boolean('worker_recovery_required')->default(false)->after('worker_sync_checked_at');
            $table->timestamp('worker_recreated_at')->nullable()->after('worker_recovery_required');
        });
    }

    public function down(): void
    {
        Schema::table('cells', function (Blueprint $table) {
            $table->dropColumn([
                'worker_recovery_required',
                'worker_recreated_at',
            ]);
        });
    }
};