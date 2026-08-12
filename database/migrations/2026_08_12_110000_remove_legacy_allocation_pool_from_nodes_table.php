<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            if (Schema::hasColumn('nodes', 'allocation_ips')) {
                $table->dropColumn('allocation_ips');
            }
            if (Schema::hasColumn('nodes', 'allocation_port_start')) {
                $table->dropColumn('allocation_port_start');
            }
            if (Schema::hasColumn('nodes', 'allocation_port_end')) {
                $table->dropColumn('allocation_port_end');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->json('allocation_ips')->nullable();
            $table->unsignedInteger('allocation_port_start')->default(25565);
            $table->unsignedInteger('allocation_port_end')->default(25600);
        });
    }
};