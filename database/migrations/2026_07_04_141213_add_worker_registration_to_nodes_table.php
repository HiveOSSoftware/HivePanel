<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->string('registration_token')->nullable()->after('api_token');
            $table->timestamp('registration_token_expires_at')->nullable()->after('registration_token');

            $table->boolean('is_registered')->default(false)->after('registration_token_expires_at');
            $table->timestamp('registered_at')->nullable()->after('is_registered');
            $table->timestamp('last_seen_at')->nullable()->after('registered_at');

            $table->string('worker_version')->nullable()->after('last_seen_at');
            $table->string('worker_hostname')->nullable()->after('worker_version');
            $table->string('worker_platform')->nullable()->after('worker_hostname');
            $table->string('worker_ip')->nullable()->after('worker_platform');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn([
                'registration_token',
                'registration_token_expires_at',
                'is_registered',
                'registered_at',
                'last_seen_at',
                'worker_version',
                'worker_hostname',
                'worker_platform',
                'worker_ip',
            ]);
        });
    }
};