<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live voice supervision: an Adviser listening (receive-only) to a connected call, with the purpose
 * and reason they gave. Nothing here stores audio. Coach notes gain a private flag for notes the
 * Adviser keeps for themself while listening (never shown to the Helper).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('voice_listens', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('call_id')->constrained('voice_calls')->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('counseling_sessions')->cascadeOnDelete();
            $table->foreignId('adviser_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose', 40);
            $table->string('reason', 500);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['call_id', 'ended_at']);
        });
        Schema::table('session_coach_notes', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('session_coach_notes', function (Blueprint $table) { $table->dropColumn('is_private'); });
        Schema::dropIfExists('voice_listens');
    }
};
