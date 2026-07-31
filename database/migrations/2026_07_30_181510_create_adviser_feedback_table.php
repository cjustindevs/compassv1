<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adviser_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('session_reports', 'id')->onDelete('cascade');
            $table->foreignId('adviser_id')->constrained('advisers', 'id')->onDelete('cascade');
            $table->string('status')->nullable();
            $table->text('feedback_text')->nullable();
            $table->text('strengths')->nullable();
            $table->text('improvement_areas')->nullable();
            $table->integer('competency_rating')->nullable();
            $table->string('competency_level')->nullable();
            $table->text('training_recommendation')->nullable();
            $table->string('follow_up_action')->nullable();
            $table->timestamp('created_date')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adviser_feedback');
    }
};