<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_migration_servers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('platform_migration_id')
                ->constrained('platform_migrations')
                ->cascadeOnDelete();

            $table->string('source_server_id', 150);
            $table->string('source_uuid', 100)->nullable();
            $table->string('name', 150);
            $table->string('owner_email')->nullable();
            $table->string('source_node_name')->nullable();
            $table->string('source_egg_name')->nullable();

            $table->json('source_metadata');
            $table->json('source_allocations')->nullable();

            $table->foreignUuid('destination_node_id')
                ->nullable()
                ->constrained('nodes')
                ->nullOnDelete();

            $table->foreignUuid('destination_owner_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUuid('destination_cell_id')
                ->nullable()
                ->constrained('cells')
                ->nullOnDelete();

            $table->string('destination_comb')->nullable();

            $table->string('status', 50)->default('discovered');
            $table->string('current_stage', 100)->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->text('error')->nullable();

            $table->timestamps();

            $table->unique(
                ['platform_migration_id', 'source_server_id'],
                'platform_migration_server_source_unique'
            );

            $table->index(['platform_migration_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_migration_servers');
    }
};