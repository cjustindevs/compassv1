<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('readiness_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('helper_id')->constrained('helpers', 'id')->onDelete('cascade');
            $table->string('availability_status')->default('available');
            $table->timestamp('shift_start')->nullable();
            $table->timestamp('shift_end')->nullable();
            $table->string('assessment_result')->nullable();
            $table->boolean('emotionally_ready')->default(false);
            $table->boolean('willing_to_listen')->default(false);
            $table->enum('stress_level', ['low', 'moderate', 'high'])->nullable();
            $table->timestamp('assessment_date')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readiness_checks');
    }
};