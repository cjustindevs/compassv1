<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->nullable()->constrained('counseling_sessions', 'id')->onDelete('set null');
            $table->foreignId('user_account_id')->constrained('users', 'id')->onDelete('cascade');
            $table->foreignId('moderator_id')->nullable()->constrained('moderators', 'id')->onDelete('set null');
            $table->string('incident_category');
            $table->text('description');
            $table->text('immediate_action')->nullable();
            $table->enum('risk_level', ['low', 'moderate', 'high', 'emergency'])->default('low');
            $table->text('recommendation')->nullable();
            $table->text('comments')->nullable();
            $table->enum('status', ['open', 'under_review', 'escalated', 'resolved', 'closed'])->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_reports');
    }
};