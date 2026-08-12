<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_migrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source_type', 50);
            $table->string('name', 150);
            $table->json('source_config');
            $table->string('status', 50)->default('pending');
            $table->string('current_stage', 100)->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('discovered_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_migrations');
    }
};