<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counseling_sessions', function (Blueprint $table) {
            $table->id(); // Primary key 'id' (Laravel default)
            $table->foreignId('seeker_id')->constrained('help_seekers', 'id')->onDelete('cascade');
            $table->foreignId('helper_id')->nullable()->constrained('helpers', 'id')->onDelete('set null');
            $table->foreignId('moderator_id')->nullable()->constrained('moderators', 'id')->onDelete('set null');
            $table->foreignId('concern_id')->nullable()->constrained('concern_categories', 'id')->onDelete('set null');
            $table->timestamp('scheduled_start')->nullable();
            $table->enum('session_type', ['chat', 'voice'])->default('chat');
            $table->enum('session_status', ['scheduled', 'active', 'completed', 'cancelled', 'no_show'])->default('scheduled');
            $table->boolean('voice_recording_consent')->default(false);
            $table->enum('risk_level', ['low', 'moderate', 'high', 'emergency'])->default('low');
            $table->boolean('escalation_required')->default(false);
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->integer('duration')->nullable();
            $table->timestamp('created_date')->useCurrent();
            $table->enum('completion_status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counseling_sessions');
    }
};