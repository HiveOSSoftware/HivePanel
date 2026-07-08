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
        Schema::create('nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');
            $table->string('location')->nullable();

            $table->string('scheme')->default('http');
            $table->string('fqdn');
            $table->unsignedInteger('port')->default(8080);

            $table->text('api_token')->nullable();

            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(['fqdn', 'port']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};
