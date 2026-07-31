<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seeker_id')->constrained('help_seekers', 'id')->onDelete('cascade');
            $table->foreignId('moderator_id')->nullable()->constrained('moderators', 'id')->onDelete('set null');
            $table->timestamp('request_date')->useCurrent();
            $table->timestamp('scheduled_date')->nullable();
            $table->enum('request_status', ['waiting', 'assigned', 'cancelled', 'expired'])->default('waiting');
            $table->enum('priority_level', ['low', 'moderate', 'high', 'emergency'])->default('low');
            $table->enum('preferred_session_type', ['chat', 'voice'])->default('chat');
            $table->foreignId('assigned_helper_id')->nullable()->constrained('helpers', 'id')->onDelete('set null');
            $table->integer('queue_position')->nullable();
            $table->integer('estimated_wait')->nullable();
            $table->timestamp('matched_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_requests');
    }
};