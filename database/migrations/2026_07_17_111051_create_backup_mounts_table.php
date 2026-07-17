<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_mounts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('cell_id')->constrained('cells')->cascadeOnDelete();
            $table->foreignUuid('backup_id')->constrained('backups')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('mounting');
            $table->string('worker_mount_path')->nullable();
            $table->text('failure_reason')->nullable();
            $table->unsignedBigInteger('extracted_size')->default(0);
            $table->timestamp('mounted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('unmounted_at')->nullable();

            $table->timestamps();

            $table->index([
                'cell_id',
                'status',
            ]);

            $table->index([
                'backup_id',
                'status',
            ]);

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_mounts');
    }
};