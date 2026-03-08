<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::drop('voice_transcripts');
        Schema::drop('voice_commands');
        Schema::drop('voice_sessions');

        Schema::create('voice_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->datetime('started_at');
            $table->datetime('ended_at')->nullable();
        });

        Schema::create('voice_transcripts', function (Blueprint $table) {
            $table->id();
            $table->string('session_id');
            $table->text('text');
            $table->string('language')->nullable();
            $table->float('confidence')->nullable();
            $table->integer('audio_duration_ms')->nullable();
            $table->datetime('started_at');
            $table->datetime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->index('session_id');
        });

        Schema::create('voice_commands', function (Blueprint $table) {
            $table->id();
            $table->string('session_id');
            $table->string('trigger_type');
            $table->text('trigger_text');
            $table->text('context_text')->nullable();
            $table->text('response_text')->nullable();
            $table->string('status')->default('pending');
            $table->datetime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
        });
    }

    public function down(): void
    {
        // No reversal — original tables had guild_id/channel_id columns
    }
};
