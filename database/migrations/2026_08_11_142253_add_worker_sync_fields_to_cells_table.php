<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cells', function (Blueprint $table) {
            $table->string('worker_sync_status')->nullable()->after('installed_at');
            $table->string('worker_sync_message')->nullable()->after('worker_sync_status');
            $table->json('worker_sync_differences')->nullable()->after('worker_sync_message');
            $table->timestamp('worker_sync_checked_at')->nullable()->after('worker_sync_differences');
        });
    }

    public function down(): void
    {
        Schema::table('cells', function (Blueprint $table) {
            $table->dropColumn([
                'worker_sync_status',
                'worker_sync_message',
                'worker_sync_differences',
                'worker_sync_checked_at',
            ]);
        });
    }
};