<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->json('allocation_ips')->nullable();
            $table->unsignedInteger('allocation_port_start')->default(25565);
            $table->unsignedInteger('allocation_port_end')->default(25600);
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn([
                'allocation_ips',
                'allocation_port_start',
                'allocation_port_end',
            ]);
        });
    }
};