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
        // Separate table for vector embeddings to keep main memories table lean
        Schema::create('memory_embeddings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('memory_id')->unique()->index();
            $table->json('embedding'); // Store 1536-dimensional vector as JSON array
            $table->string('model', 100)->default('text-embedding-3-small'); // Track embedding model
            $table->integer('dimensions')->default(1536); // Track vector dimensions
            $table->timestamps();

            $table->foreign('memory_id')->references('id')->on('memories')->onDelete('cascade');

            // Index for faster lookups
            $table->index(['model', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memory_embeddings');
    }
};
