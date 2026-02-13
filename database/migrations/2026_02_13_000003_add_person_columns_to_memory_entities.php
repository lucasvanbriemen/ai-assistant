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
        Schema::table('memory_entities', function (Blueprint $table) {
            // Common person attributes as real columns (enforces consistency)
            $table->string('email', 255)->nullable()->index()->after('attributes');
            $table->string('phone', 50)->nullable()->after('email');
            $table->string('job_title', 255)->nullable()->index()->after('phone');
            $table->string('company', 255)->nullable()->index()->after('job_title');
            $table->string('department', 255)->nullable()->after('company');
            $table->string('work_location', 255)->nullable()->after('department');
            $table->date('birthday')->nullable()->after('work_location');
            $table->text('address')->nullable()->after('birthday');
            $table->string('relationship_type', 100)->nullable()->after('address'); // For family: spouse, parent, sibling

            // Additional contact fields
            $table->string('secondary_email', 255)->nullable()->after('relationship_type');
            $table->string('secondary_phone', 50)->nullable()->after('secondary_email');

            // Composite indexes for common queries
            $table->index(['entity_type', 'company']);
            $table->index(['entity_type', 'job_title']);
            $table->index(['entity_type', 'relationship_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memory_entities', function (Blueprint $table) {
            $table->dropIndex(['entity_type', 'company']);
            $table->dropIndex(['entity_type', 'job_title']);
            $table->dropIndex(['entity_type', 'relationship_type']);

            $table->dropColumn([
                'email',
                'phone',
                'job_title',
                'company',
                'department',
                'work_location',
                'birthday',
                'address',
                'relationship_type',
                'secondary_email',
                'secondary_phone',
            ]);
        });
    }
};
