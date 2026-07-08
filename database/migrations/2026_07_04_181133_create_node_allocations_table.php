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
            $table->id();
            $table->foreignId('node_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cell_id')->nullable()->constrained()->nullOnDelete();

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
