<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screening_responses', function (Blueprint $table) {
            $table->string('follow_up_note', 300)->nullable();
            $table->foreignId('follow_up_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('follow_up_requested_at')->nullable();
            $table->string('follow_up_answer', 32)->nullable();
            $table->timestamp('follow_up_answered_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('screening_responses', function (Blueprint $table) {
            $table->dropForeign(['follow_up_requested_by']);
            $table->dropColumn(['follow_up_note', 'follow_up_requested_by', 'follow_up_requested_at', 'follow_up_answer', 'follow_up_answered_at']);
        });
    }
};
