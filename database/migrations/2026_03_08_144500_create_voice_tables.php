<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
        });

        Schema::create('voice_transcripts', function (Blueprint $table) {
            $table->id();
            $table->text('text');
            $table->string('language')->nullable();
            $table->float('confidence')->nullable();
            $table->integer('audio_duration_ms')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('created_at')->useCurrent();
            $table->string('session_id');

            $table->index('started_at');
            $table->index('session_id');
        });

        Schema::create('voice_commands', function (Blueprint $table) {
            $table->id();
            $table->string('session_id');
            $table->string('trigger_type');
            $table->text('trigger_text');
            $table->text('context_text')->nullable();
            $table->text('response_text')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('status')->default('pending');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_commands');
        Schema::dropIfExists('voice_transcripts');
        Schema::dropIfExists('voice_sessions');
    }
};
