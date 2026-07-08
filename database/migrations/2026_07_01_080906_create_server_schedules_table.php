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
        Schema::create('server_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('cell_id')->index();

            $table->string('name');
            $table->string('cron_expression');
            $table->string('timezone')->default('UTC');

            $table->boolean('enabled')->default(true);
            $table->boolean('only_when_online')->default(false);
            $table->boolean('continue_on_failure')->default(false);

            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_schedules');
    }
};
