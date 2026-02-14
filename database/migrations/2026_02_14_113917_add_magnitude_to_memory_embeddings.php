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
        Schema::table('memory_embeddings', function (Blueprint $table) {
            // Add pre-computed magnitude for faster cosine similarity
            $table->decimal('magnitude', 10, 6)->nullable()->after('dimensions');
            $table->index('magnitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memory_embeddings', function (Blueprint $table) {
            $table->dropIndex(['magnitude']);
            $table->dropColumn('magnitude');
        });
    }
};
