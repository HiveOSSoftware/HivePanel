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
        Schema::create('server_schedule_run_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_schedule_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_schedule_action_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedInteger('sort_order')->default(0);
            $table->string('type');
            $table->string('status')->default('running');

            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();

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
        Schema::dropIfExists('server_schedule_run_actions');
    }
};
