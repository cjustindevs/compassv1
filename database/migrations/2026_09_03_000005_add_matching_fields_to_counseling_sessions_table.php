<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counseling_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('counseling_sessions', 'queue_request_id')) {
                $table->foreignId('queue_request_id')->nullable()->after('moderator_id')->constrained('queue_requests', 'id')->nullOnDelete();
            }
            if (! Schema::hasColumn('counseling_sessions', 'match_method')) {
                $table->string('match_method')->nullable()->after('session_type');
            }
            if (! Schema::hasColumn('counseling_sessions', 'matched_by')) {
                $table->string('matched_by')->nullable()->after('match_method');
            }
            if (! Schema::hasColumn('counseling_sessions', 'pre_session_brief_expires_at')) {
                $table->timestamp('pre_session_brief_expires_at')->nullable()->after('scheduled_start');
            }
            if (! Schema::hasColumn('counseling_sessions', 'seeker_evaluation_submitted')) {
                $table->boolean('seeker_evaluation_submitted')->default(false)->after('completion_status');
            }
            if (! Schema::hasColumn('counseling_sessions', 'auto_completed')) {
                $table->boolean('auto_completed')->default(false)->after('seeker_evaluation_submitted');
            }
            if (! Schema::hasColumn('counseling_sessions', 'auto_completed_at')) {
                $table->timestamp('auto_completed_at')->nullable()->after('auto_completed');
            }
            if (! Schema::hasColumn('counseling_sessions', 'no_show')) {
                $table->boolean('no_show')->default(false)->after('auto_completed_at');
            }
            if (! Schema::hasColumn('counseling_sessions', 'abandoned')) {
                $table->boolean('abandoned')->default(false)->after('no_show');
            }
            if (! Schema::hasColumn('counseling_sessions', 'abandoned_at')) {
                $table->timestamp('abandoned_at')->nullable()->after('abandoned');
            }
        });
    }

    public function down(): void
    {
        Schema::table('counseling_sessions', function (Blueprint $table) {
            foreach (['queue_request_id', 'match_method', 'matched_by', 'pre_session_brief_expires_at', 'seeker_evaluation_submitted', 'auto_completed', 'auto_completed_at', 'no_show', 'abandoned', 'abandoned_at'] as $column) {
                if (Schema::hasColumn('counseling_sessions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
