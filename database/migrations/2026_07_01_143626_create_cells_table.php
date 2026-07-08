<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cells', function (Blueprint $table) {
            // HivePanel's own UUID for the server.
            $table->uuid('id')->primary();

            // Which node currently hosts this server.
            $table->foreignUuid('node_id')
                ->constrained('nodes')
                ->cascadeOnDelete();

            // Owner of the server.
            $table->foreignId('owner_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // UUID used by the worker on this node.
            $table->uuid('daemon_id');

            // Basic information.
            $table->string('name');
            $table->string('comb')->nullable();

            // Panel-specific metadata.
            $table->json('metadata')->nullable();

            $table->timestamps();

            // A daemon ID only needs to be unique within a node.
            $table->unique(['node_id', 'daemon_id']);

            // Helpful indexes.
            $table->index('owner_id');
            $table->index('node_id');
            $table->index('daemon_id');
            $table->index('comb');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cells');
    }
};