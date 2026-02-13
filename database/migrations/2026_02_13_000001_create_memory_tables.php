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
        // Main memories table - stores notes, reminders, facts, preferences, transcripts
        Schema::create('memories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index(); // For future multi-user support
            $table->string('type', 50)->index(); // note, reminder, fact, preference, transcript, etc.
            $table->text('content'); // Main content
            $table->text('summary')->nullable(); // AI-generated summary for large content
            $table->integer('content_length')->default(0); // Character count
            $table->string('content_hash', 64)->nullable()->index(); // SHA-256 for duplicate detection
            $table->json('metadata')->nullable(); // Flexible JSON for type-specific data
            $table->decimal('relevance_score', 5, 2)->default(1.00)->index(); // Importance/relevance (0-100)
            $table->timestamp('reminder_at')->nullable()->index(); // For time-based reminders
            $table->boolean('is_archived')->default(false)->index(); // Soft delete alternative
            $table->timestamp('last_accessed_at')->nullable(); // Track usage for relevance decay
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
            $table->json('attributes')->nullable(); // Flexible schema: job_title, location, phone, etc.
            $table->integer('mention_count')->default(1)->index(); // Track importance by mentions
            $table->timestamp('last_mentioned_at')->nullable(); // When last referenced
            $table->boolean('is_active')->default(true)->index(); // Archive inactive entities
            $table->timestamps();

            // Composite indexes
            $table->index(['entity_type', 'is_active']);
            $table->index(['user_id', 'entity_type', 'is_active']);

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
            $table->timestamps();

            $table->foreign('from_entity_id')->references('id')->on('memory_entities')->onDelete('cascade');
            $table->foreign('to_entity_id')->references('id')->on('memory_entities')->onDelete('cascade');

            // Ensure unique relationships
            $table->unique(['from_entity_id', 'to_entity_id', 'relationship_type'], 'unique_relationship');

            // Index for reverse lookups
            $table->index(['to_entity_id', 'relationship_type']);
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memory_tag_links');
        Schema::dropIfExists('memory_tags');
        Schema::dropIfExists('memory_entity_links');
        Schema::dropIfExists('memory_relationships');
        Schema::dropIfExists('memory_entities');
        Schema::dropIfExists('memories');
    }
};
