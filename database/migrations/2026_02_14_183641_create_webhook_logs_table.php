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
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('service', 50)->index(); // email, calendar, slack, etc.
            $table->json('payload'); // The webhook payload
            $table->string('status', 20)->default('pending')->index(); // pending, processing, completed, failed
            $table->text('error_message')->nullable(); // Error details if failed
            $table->string('ip_address', 45)->nullable(); // IPv4 or IPv6
            $table->string('user_agent')->nullable(); // Request user agent
            $table->timestamp('processed_at')->nullable(); // When job was processed
            $table->timestamps();

            $table->index(['service', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
