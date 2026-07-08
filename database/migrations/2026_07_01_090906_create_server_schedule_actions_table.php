<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('server_schedule_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('server_schedule_id')
                ->constrained('server_schedules')
                ->cascadeOnDelete();

            $table->unsignedInteger('sort_order')->default(0);

            $table->string('type'); 
            // command, backup, start, stop, restart, wait, utility

            $table->json('payload')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_schedule_actions');
    }
};
