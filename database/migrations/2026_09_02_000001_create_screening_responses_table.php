<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screening_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seeker_id')->constrained('help_seekers', 'id')->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('counseling_sessions', 'id')->nullOnDelete();
            $table->json('responses');
            $table->enum('risk_level', ['low', 'moderate', 'high', 'emergency']);
            $table->unsignedTinyInteger('priority');
            $table->string('action');
            $table->text('reason')->nullable();
            $table->timestamp('classified_at')->useCurrent();
            $table->string('classified_by')->default('system');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screening_responses');
    }
};
