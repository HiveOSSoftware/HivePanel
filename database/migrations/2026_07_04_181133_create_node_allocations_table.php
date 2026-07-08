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
        Schema::create('node_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('node_id')->constrained('nodes')->cascadeOnDelete();
            $table->foreignUuid('cell_id')->nullable()->constrained('cells')->nullOnDelete();

            $table->string('ip');
            $table->unsignedInteger('port');
            $table->string('alias')->nullable();
            $table->text('notes')->nullable();

            $table->boolean('is_reserved')->default(false);

            $table->timestamps();

            $table->unique(['node_id', 'ip', 'port']);
            $table->index(['node_id', 'cell_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('node_allocations');
    }
};
