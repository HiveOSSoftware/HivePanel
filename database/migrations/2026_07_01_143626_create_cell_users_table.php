<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cell_users', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('cell_id')
                ->constrained('cells')
                ->cascadeOnDelete();

            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->json('permissions')->nullable();

            $table->timestamps();

            $table->unique(['cell_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cell_users');
    }
};