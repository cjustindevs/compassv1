<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Private coaching notes an Adviser sends to their Helper during a live session.
 * Only the Helper and supervising Adviser can read them; the Seeker never sees them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_coach_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('counseling_sessions')->cascadeOnDelete();
            $table->foreignId('adviser_id')->constrained('advisers');
            $table->foreignId('helper_id')->constrained('helpers');
            $table->text('note');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['session_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_coach_notes');
    }
};
