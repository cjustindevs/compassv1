<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seeker_id')->constrained('help_seekers', 'id')->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('counseling_sessions', 'id')->nullOnDelete();
            $table->foreignId('adviser_id')->nullable()->constrained('advisers', 'id')->nullOnDelete();
            $table->foreignId('referral_id')->nullable()->constrained('referrals', 'id')->nullOnDelete();
            $table->string('alert_type')->default('safety_threat');
            $table->enum('risk_level', ['emergency'])->default('emergency');
            $table->timestamp('triggered_at')->useCurrent();
            $table->foreignId('triggered_by')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->text('trigger_reason');
            $table->enum('status', ['pending', 'notified', 'referred', 'resolved', 'closed'])->default('pending');
            $table->boolean('notification_sent')->default(false);
            $table->boolean('adviser_notified')->default(false);
            $table->boolean('professional_referred')->default(false);
            $table->timestamp('adviser_notified_at')->nullable();
            $table->timestamp('notification_sent_at')->nullable();
            $table->timestamp('professional_referred_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_alerts');
    }
};
