<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_seeker_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('counseling_sessions', 'id')->onDelete('cascade');
            $table->integer('helpfulness_score')->nullable();
            $table->integer('comfort_score')->nullable();
            $table->integer('feeling_after_score')->nullable();
            $table->integer('understood_score')->nullable();
            $table->integer('reuse_score')->nullable();
            $table->text('comments')->nullable();
            $table->decimal('overall_score', 3, 1)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_seeker_evaluations');
    }
};