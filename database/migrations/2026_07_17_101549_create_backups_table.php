<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('cell_id')->constrained('cells')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');
            $table->string('status', 32)->default('pending');
            $table->string('archive_name')->nullable();
            $table->unsignedBigInteger('size')->default(0);

            $table->string('checksum', 128)->nullable();
            $table->string('checksum_algorithm', 16)->nullable();
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_automatic')->default(false);
            $table->json('ignored_files')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            $table->softDeletes();
            $table->timestamps();

            $table->index(['cell_id', 'deleted_at', 'created_at']);
            $table->index(['cell_id', 'status']);
            $table->index(['cell_id', 'is_locked']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};