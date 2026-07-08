<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_live_stats', function (Blueprint $table) {
            $table->id();

            $table->foreignUuid('node_id')
                ->unique()
                ->constrained('nodes')
                ->cascadeOnDelete();

            $table->decimal('host_cpu_used', 8, 2)->default(0);
            $table->decimal('host_cpu_max', 8, 2)->default(0);

            $table->decimal('host_memory_used_gb', 10, 2)->default(0);
            $table->decimal('host_memory_max_gb', 10, 2)->default(0);

            $table->decimal('host_disk_used_gb', 10, 2)->default(0);
            $table->decimal('host_disk_max_gb', 10, 2)->default(0);

            $table->decimal('cells_cpu_used', 8, 2)->default(0);
            $table->decimal('cells_memory_used_gb', 10, 2)->default(0);
            $table->decimal('cells_disk_used_gb', 10, 2)->default(0);

            $table->unsignedInteger('cells_total')->default(0);
            $table->unsignedInteger('cells_running')->default(0);

            $table->json('raw')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_live_stats');
    }
};