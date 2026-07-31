<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helper_competency_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('helper_id')->constrained('helpers', 'id')->onDelete('cascade');
            $table->foreignId('adviser_id')->constrained('advisers', 'id')->onDelete('cascade');
            $table->timestamp('evaluation_date')->useCurrent();
            $table->decimal('active_listening_score', 3, 1)->nullable();
            $table->decimal('empathy_score', 3, 1)->nullable();
            $table->decimal('respect_score', 3, 1)->nullable();
            $table->decimal('ethical_practices_score', 3, 1)->nullable();
            $table->decimal('referral_accuracy_score', 3, 1)->nullable();
            $table->string('appeal_status')->nullable();
            $table->decimal('overall_score', 3, 1)->nullable();
            $table->string('competency_level')->nullable();
            $table->string('evaluation_period')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helper_competency_history');
    }
};