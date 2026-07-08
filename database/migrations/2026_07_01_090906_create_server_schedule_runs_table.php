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
        Schema::create('server_schedule_runs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('server_schedule_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('status')->default('pending');
            // pending, running, success, failed

            $table->longText('output')->nullable();
            $table->longText('error')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_schedule_runs');
    }
};
