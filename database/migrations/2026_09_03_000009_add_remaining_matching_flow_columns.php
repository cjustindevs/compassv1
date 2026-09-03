<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpers', function (Blueprint $table) {
            foreach ([
                'is_under_review' => fn () => $table->boolean('is_under_review')->default(false),
                'review_reason' => fn () => $table->text('review_reason')->nullable(),
                'current_shift_sessions' => fn () => $table->unsignedInteger('current_shift_sessions')->default(0),
                'break_started_at' => fn () => $table->timestamp('break_started_at')->nullable(),
                'last_readiness_at' => fn () => $table->timestamp('last_readiness_at')->nullable(),
                'specialties' => fn () => $table->json('specialties')->nullable(),
            ] as $column => $definition) {
                if (! Schema::hasColumn('helpers', $column)) {
                    $definition();
                }
            }
        });

        Schema::table('queue_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('queue_requests', 'voice_consent')) {
                $table->boolean('voice_consent')->default(false);
            }
        });

        Schema::table('counseling_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('counseling_sessions', 'voice_consent_obtained')) {
                $table->boolean('voice_consent_obtained')->default(false);
            }
            if (! Schema::hasColumn('counseling_sessions', 'concern_category')) {
                $table->string('concern_category')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('counseling_sessions', function (Blueprint $table) {
            foreach (['voice_consent_obtained', 'concern_category'] as $column) {
                if (Schema::hasColumn('counseling_sessions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('queue_requests', function (Blueprint $table) {
            if (Schema::hasColumn('queue_requests', 'voice_consent')) {
                $table->dropColumn('voice_consent');
            }
        });
    }
};
