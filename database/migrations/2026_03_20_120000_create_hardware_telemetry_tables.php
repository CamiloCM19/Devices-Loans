<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hardware_telemetry_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_uuid')->unique();
            $table->string('session_type', 32);
            $table->string('source')->nullable()->index();
            $table->string('page_name')->nullable();
            $table->string('page_path')->nullable();
            $table->string('page_url')->nullable();
            $table->string('status', 32)->default('active');
            $table->unsignedInteger('timeout_seconds')->default(60);
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('ended_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('hardware_telemetry_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hardware_telemetry_session_id')
                ->nullable()
                ->constrained('hardware_telemetry_sessions')
                ->nullOnDelete();
            $table->string('event_uuid')->unique();
            $table->string('session_uuid')->nullable()->index();
            $table->string('session_type', 32)->nullable()->index();
            $table->string('channel', 32)->index();
            $table->string('event_type', 120)->index();
            $table->string('level', 16)->default('info')->index();
            $table->string('source')->nullable()->index();
            $table->string('uid')->nullable()->index();
            $table->string('correlation_id')->nullable()->index();
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hardware_telemetry_events');
        Schema::dropIfExists('hardware_telemetry_sessions');
    }
};
