<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('counseling_sessions', 'id')->onDelete('cascade');
            $table->foreignId('helper_id')->constrained('helpers', 'id')->onDelete('cascade');
            $table->foreignId('moderator_id')->nullable()->constrained('moderators', 'id')->onDelete('set null');
            $table->foreignId('adviser_id')->nullable()->constrained('advisers', 'id')->onDelete('set null');
            $table->foreignId('professional_id')->nullable()->constrained('psychology_professionals', 'id')->onDelete('set null');
            $table->enum('priority_level', ['low', 'moderate', 'high', 'emergency'])->default('low');
            $table->boolean('help_seeker_consent')->default(false);
            $table->boolean('identity_disclosed')->default(false);
            $table->text('referral_reason');
            $table->timestamp('referral_date')->useCurrent();
            $table->enum('status', ['pending_adviser', 'pending_professional', 'accepted', 'declined', 'completed', 'closed'])->default('pending_adviser');
            $table->timestamp('closed_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};