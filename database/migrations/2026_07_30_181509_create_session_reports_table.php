<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('counseling_sessions', 'id')->onDelete('cascade');
            $table->text('help_seeker_condition')->nullable();
            $table->boolean('referral_recommended')->default(false);
            $table->text('session_summary');
            $table->text('personal_reflection')->nullable();
            $table->boolean('adviser_reviewed')->default(false);
            $table->timestamp('reviewed_date')->nullable();
            $table->timestamp('created_date')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_reports');
    }
};