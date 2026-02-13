<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Migrate all column data to JSON before dropping columns
        $this->migrateColumnDataToJson();

        // Step 2: Drop the specific columns, keep only email and phone
        Schema::table('memory_entities', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['entity_type', 'company']);
            $table->dropIndex(['entity_type', 'job_title']);
            $table->dropIndex(['entity_type', 'relationship_type']);

            // Drop all type-specific columns except email and phone
            $table->dropColumn([
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

        echo "✓ Simplified schema to: email, phone + JSON attributes\n";
        echo "✓ All existing data preserved in JSON\n";
    }

    /**
     * Migrate existing column data to JSON attributes
     */
    private function migrateColumnDataToJson(): void
    {
        $entities = DB::table('memory_entities')->get();

        foreach ($entities as $entity) {
            $currentAttributes = json_decode($entity->attributes, true) ?? [];

            // Add column data to attributes if not null
            $columnsToMigrate = [
                'job_title',
                'company',
                'department',
                'work_location',
                'birthday',
                'address',
                'relationship_type',
                'secondary_email',
                'secondary_phone',
            ];

            foreach ($columnsToMigrate as $column) {
                if (isset($entity->$column) && $entity->$column !== null) {
                    $currentAttributes[$column] = $entity->$column;
                }
            }

            // Update the attributes JSON
            DB::table('memory_entities')
                ->where('id', $entity->id)
                ->update(['attributes' => json_encode($currentAttributes)]);
        }

        echo "✓ Migrated " . count($entities) . " entities to JSON format\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore the columns
        Schema::table('memory_entities', function (Blueprint $table) {
            $table->string('job_title', 255)->nullable()->index();
            $table->string('company', 255)->nullable()->index();
            $table->string('department', 255)->nullable();
            $table->string('work_location', 255)->nullable();
            $table->date('birthday')->nullable();
            $table->text('address')->nullable();
            $table->string('relationship_type', 100)->nullable()->index();
            $table->string('secondary_email', 255)->nullable();
            $table->string('secondary_phone', 50)->nullable();

            // Restore composite indexes
            $table->index(['entity_type', 'company']);
            $table->index(['entity_type', 'job_title']);
            $table->index(['entity_type', 'relationship_type']);
        });

        // Migrate data back from JSON to columns
        $entities = DB::table('memory_entities')->get();

        foreach ($entities as $entity) {
            $attributes = json_decode($entity->attributes, true) ?? [];

            $columnData = [];
            $columnsToRestore = [
                'job_title', 'company', 'department', 'work_location',
                'birthday', 'address', 'relationship_type',
                'secondary_email', 'secondary_phone'
            ];

            foreach ($columnsToRestore as $column) {
                if (isset($attributes[$column])) {
                    $columnData[$column] = $attributes[$column];
                    unset($attributes[$column]);
                }
            }

            // Update columns and remaining JSON
            $columnData['attributes'] = json_encode($attributes);
            DB::table('memory_entities')
                ->where('id', $entity->id)
                ->update($columnData);
        }
    }
};
