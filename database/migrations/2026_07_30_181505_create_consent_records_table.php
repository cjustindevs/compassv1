<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seeker_id')->constrained('help_seekers', 'id')->onDelete('cascade');
            $table->enum('document_type', ['privacy_policy', 'informed_consent', 'voice_recording'])->default('informed_consent');
            $table->boolean('consent_given')->default(false);
            $table->timestamp('consent_date')->useCurrent();
            $table->boolean('withdrawn')->default(false);
            $table->timestamp('withdrawn_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
    }
};