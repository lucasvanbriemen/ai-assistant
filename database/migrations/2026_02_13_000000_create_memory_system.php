<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Comprehensive memory system with entities, relationships, embeddings, and temporal tracking.
     */
    public function up(): void
    {
        Schema::create('memories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('type', 50)->index();
            $table->text('content');
            $table->text('summary')->nullable();
            $table->integer('content_length')->default(0);
            $table->string('content_hash', 64)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->decimal('relevance_score', 5, 2)->default(1.00)->index();
            $table->timestamp('reminder_at')->nullable()->index();
            $table->boolean('is_archived')->default(false)->index();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();

            // Composite indexes for common queries
            $table->index(['type', 'is_archived', 'created_at']);
            $table->index(['user_id', 'type', 'is_archived']);
            $table->index(['reminder_at', 'is_archived']);

            // Full-text search index
            $table->fullText(['content', 'summary']);
        });

        // Entities table - stores people, places, organizations, services
        Schema::create('memory_entities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('entity_type', 50)->index(); // person, place, organization, service
            $table->string('entity_subtype', 50)->nullable(); // colleague, family, friend, etc.
            $table->string('name')->index(); // Full name or identifier
            $table->text('description')->nullable(); // Description or context
            $table->text('summary')->nullable(); // AI-generated summary of entity
            $table->json('attributes')->nullable(); // Flexible JSON: job_title, company, birthday, address, etc.

            // Only email and phone as real columns (most commonly queried)
            $table->string('email', 255)->nullable()->index();
            $table->string('phone', 50)->nullable();

            $table->integer('mention_count')->default(1)->index(); // Track importance by mentions
            $table->timestamp('last_mentioned_at')->nullable(); // When last referenced
            $table->boolean('is_active')->default(true)->index(); // Archive inactive entities

            // Temporal tracking
            $table->date('start_date')->nullable()->comment('When this entity/relationship started');
            $table->date('end_date')->nullable()->comment('When this entity/relationship ended or will end');

            $table->timestamps();

            // Composite indexes
            $table->index(['entity_type', 'is_active']);
            $table->index(['user_id', 'entity_type', 'is_active']);
            $table->index(['entity_type', 'end_date'], 'idx_entity_type_end_date');

            // Full-text search on name and description
            $table->fullText(['name', 'description']);
        });

        // Relationships between entities (e.g., works_at, lives_at, reports_to)
        Schema::create('memory_relationships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_entity_id')->index();
            $table->unsignedBigInteger('to_entity_id')->index();
            $table->string('relationship_type', 100); // works_at, lives_at, reports_to, friend_of, etc.
            $table->json('metadata')->nullable(); // Additional context

            // Temporal tracking for relationships
            $table->date('started_at')->nullable()->comment('When this relationship started');
            $table->date('ended_at')->nullable()->comment('When this relationship ended or will end');

            $table->timestamps();

            $table->foreign('from_entity_id')->references('id')->on('memory_entities')->onDelete('cascade');
            $table->foreign('to_entity_id')->references('id')->on('memory_entities')->onDelete('cascade');

            // Ensure unique relationships
            $table->unique(['from_entity_id', 'to_entity_id', 'relationship_type'], 'unique_relationship');

            // Index for reverse lookups and temporal queries
            $table->index(['to_entity_id', 'relationship_type']);
            $table->index(['relationship_type', 'ended_at'], 'idx_relationship_type_ended_at');
        });

        // Many-to-many junction table between memories and entities
        Schema::create('memory_entity_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('memory_id')->index();
            $table->unsignedBigInteger('entity_id')->index();
            $table->string('link_type', 50)->default('mentioned'); // mentioned, about, created_by, etc.
            $table->timestamps();

            $table->foreign('memory_id')->references('id')->on('memories')->onDelete('cascade');
            $table->foreign('entity_id')->references('id')->on('memory_entities')->onDelete('cascade');

            // Composite index for queries
            $table->index(['memory_id', 'entity_id']);
            $table->index(['entity_id', 'memory_id']);
        });

        // Tags for categorization
        Schema::create('memory_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('category', 50)->nullable(); // work, personal, hobby, etc.
            $table->integer('usage_count')->default(0)->index(); // Track popularity
            $table->timestamps();
        });

        // Many-to-many junction for tags
        Schema::create('memory_tag_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('memory_id')->index();
            $table->unsignedBigInteger('tag_id')->index();
            $table->timestamps();

            $table->foreign('memory_id')->references('id')->on('memories')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('memory_tags')->onDelete('cascade');

            // Ensure unique tag assignments
            $table->unique(['memory_id', 'tag_id']);
        });

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
        Schema::dropIfExists('memory_tag_links');
        Schema::dropIfExists('memory_tags');
        Schema::dropIfExists('memory_entity_links');
        Schema::dropIfExists('memory_relationships');
        Schema::dropIfExists('memory_entities');
        Schema::dropIfExists('memories');
    }
};
