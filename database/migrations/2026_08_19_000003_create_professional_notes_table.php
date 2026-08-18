<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clinical intervention notes documented by the psychology professional
     * for each active case. Kept separate from session_reports (helper flow)
     * so professional documentation never overwrites helper records.
     */
    public function up(): void
    {
        Schema::create('professional_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained('referrals', 'id')->onDelete('cascade');
            $table->foreignId('professional_id')->constrained('psychology_professionals', 'id')->onDelete('cascade');
            $table->foreignId('session_id')->constrained('counseling_sessions', 'id')->onDelete('cascade');
            $table->string('intervention_type')->nullable();
            $table->text('notes');
            $table->text('follow_up_plan')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_notes');
    }
};
