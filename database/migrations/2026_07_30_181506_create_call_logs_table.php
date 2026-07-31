<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('counseling_sessions', 'id')->onDelete('cascade');
            $table->boolean('recording_consent')->default(false);
            $table->timestamp('call_start')->nullable();
            $table->timestamp('call_end')->nullable();
            $table->integer('duration')->nullable();
            $table->string('transcript_path')->nullable();
            $table->string('recording_path')->nullable();
            $table->timestamp('recording_deleted_at')->nullable();
            $table->timestamp('retention_expiry')->nullable();
            $table->string('review_status')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};