<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('voice_calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('session_id')->constrained('counseling_sessions')->cascadeOnDelete();
            $table->foreignId('caller_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('callee_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('ringing');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('caller_connected_at')->nullable();
            $table->timestamp('callee_connected_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('caller_seen_at')->nullable();
            $table->timestamp('callee_seen_at')->nullable();
            $table->string('end_reason', 40)->nullable();
            $table->timestamps();
            $table->index(['session_id', 'status']);
        });
        Schema::create('voice_turn_credentials', function (Blueprint $table) {
            $table->id();
            $table->text('payload');
            $table->timestamp('ready_at');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_turn_credentials');
        Schema::dropIfExists('voice_calls');
    }
};
