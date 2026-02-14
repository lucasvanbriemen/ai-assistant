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

            $table->index(['type', 'is_archived', 'created_at']);
            $table->index(['user_id', 'type', 'is_archived']);
            $table->index(['reminder_at', 'is_archived']);

            $table->fullText(['content', 'summary']);
        });

        Schema::create('memory_entities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('entity_type', 50)->index();
            $table->string('entity_subtype', 50)->nullable();
            $table->string('name')->index();
            $table->text('description')->nullable();
            $table->text('summary')->nullable();
            $table->json('attributes')->nullable();

            $table->string('email', 255)->nullable()->index();
            $table->string('phone', 50)->nullable();

            $table->integer('mention_count')->default(1)->index();
            $table->timestamp('last_mentioned_at')->nullable();
            $table->boolean('is_active')->default(true)->index();

            $table->date('start_date')->nullable()->comment('When this entity/relationship started');
            $table->date('end_date')->nullable()->comment('When this entity/relationship ended or will end');

            $table->timestamps();

            $table->index(['entity_type', 'is_active']);
            $table->index(['user_id', 'entity_type', 'is_active']);
            $table->index(['entity_type', 'end_date'], 'idx_entity_type_end_date');

            $table->fullText(['name', 'description']);
        });

        Schema::create('memory_relationships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_entity_id')->index();
            $table->unsignedBigInteger('to_entity_id')->index();
            $table->string('relationship_type', 100);
            $table->json('metadata')->nullable();

            $table->date('started_at')->nullable()->comment('When this relationship started');
            $table->date('ended_at')->nullable()->comment('When this relationship ended or will end');

            $table->timestamps();

            $table->foreign('from_entity_id')->references('id')->on('memory_entities')->onDelete('cascade');
            $table->foreign('to_entity_id')->references('id')->on('memory_entities')->onDelete('cascade');

            $table->unique(['from_entity_id', 'to_entity_id', 'relationship_type'], 'unique_relationship');

            $table->index(['to_entity_id', 'relationship_type']);
            $table->index(['relationship_type', 'ended_at'], 'idx_relationship_type_ended_at');
        });

        Schema::create('memory_entity_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('memory_id')->index();
            $table->unsignedBigInteger('entity_id')->index();
            $table->string('link_type', 50)->default('mentioned');
            $table->timestamps();

            $table->foreign('memory_id')->references('id')->on('memories')->onDelete('cascade');
            $table->foreign('entity_id')->references('id')->on('memory_entities')->onDelete('cascade');

            $table->index(['memory_id', 'entity_id']);
            $table->index(['entity_id', 'memory_id']);
        });

        Schema::create('memory_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('category', 50)->nullable();
            $table->integer('usage_count')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('memory_tag_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('memory_id')->index();
            $table->unsignedBigInteger('tag_id')->index();
            $table->timestamps();

            $table->foreign('memory_id')->references('id')->on('memories')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('memory_tags')->onDelete('cascade');

            $table->unique(['memory_id', 'tag_id']);
        });

        Schema::create('memory_embeddings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('memory_id')->unique()->index();
            $table->json('embedding');
            $table->string('model', 100)->default('text-embedding-3-small');
            $table->integer('dimensions')->default(1536);
            $table->timestamps();

            $table->foreign('memory_id')->references('id')->on('memories')->onDelete('cascade');

            $table->index(['model', 'created_at']);
        });
    }

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
