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
        // Add temporal tracking to memory_entities
        Schema::table('memory_entities', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('is_active')
                ->comment('When this entity/relationship started (job start date, friendship began, subscription started, etc.)');
            $table->date('end_date')->nullable()->after('start_date')
                ->comment('When this entity/relationship ended or will end (job end date, subscription cancelled, etc.)');

            // Add index for querying current entities (end_date is null or in future)
            $table->index(['entity_type', 'end_date'], 'idx_entity_type_end_date');
        });

        // Add temporal tracking to memory_relationships
        Schema::table('memory_relationships', function (Blueprint $table) {
            $table->date('started_at')->nullable()->after('metadata')
                ->comment('When this relationship started');
            $table->date('ended_at')->nullable()->after('started_at')
                ->comment('When this relationship ended or will end');

            // Add index for querying current relationships
            $table->index(['relationship_type', 'ended_at'], 'idx_relationship_type_ended_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memory_entities', function (Blueprint $table) {
            $table->dropIndex('idx_entity_type_end_date');
            $table->dropColumn(['start_date', 'end_date']);
        });

        Schema::table('memory_relationships', function (Blueprint $table) {
            $table->dropIndex('idx_relationship_type_ended_at');
            $table->dropColumn(['started_at', 'ended_at']);
        });
    }
};
