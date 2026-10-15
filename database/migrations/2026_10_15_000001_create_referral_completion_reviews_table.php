<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_completion_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained('referrals');
            $table->foreignId('professional_id')->constrained('psychology_professionals');
            $table->foreignId('submitted_by')->constrained('users');
            $table->text('summary');
            $table->boolean('follow_up_required')->default(false);
            $table->text('follow_up_notes')->nullable();
            $table->string('review_status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->text('review_notes')->nullable();
            $table->text('appointment_disposition_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['referral_id', 'review_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_completion_reviews');
    }
};
