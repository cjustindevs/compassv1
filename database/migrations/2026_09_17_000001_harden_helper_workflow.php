<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpers', function (Blueprint $t) {
            $t->string('verification_status')->default('pending');
            $t->foreignId('verified_by')->nullable()->constrained('users');
            $t->timestamp('verified_at')->nullable();
            $t->timestamp('verification_expires_at')->nullable();
            $t->boolean('training_verified')->default(false);
            $t->text('qualification_evidence')->nullable();
            $t->text('declared_specializations')->nullable();
        });
        Schema::table('readiness_checks', function (Blueprint $t) {
            $t->string('form_version')->nullable();
            $t->json('skills_confirmed')->nullable();
            $t->foreignId('helper_schedule_id')->nullable()->constrained('helper_schedules');
            $t->timestamp('expiry_notified_at')->nullable();
        });
        Schema::table('counseling_sessions', function (Blueprint $t) {
            $t->string('match_status')->nullable();
            $t->string('documentation_status')->default('pending');
            $t->string('completion_reason')->nullable();
            $t->timestamp('documentation_notified_at')->nullable();
        });
        Schema::table('session_reports', function (Blueprint $t) {
            $t->string('session_result')->nullable();
            $t->text('follow_up_plan')->nullable();
            $t->timestamp('summary_submitted_at')->nullable();
            $t->timestamp('reflection_submitted_at')->nullable();
            $t->timestamp('reassessment_requested_at')->nullable();
            $t->timestamp('reassessment_reviewed_at')->nullable();
        });
        Schema::create('session_report_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('report_id')->constrained('session_reports');
            $t->foreignId('actor_id')->constrained('users');
            $t->json('snapshot');
            $t->string('reason', 1000);
            $t->timestamp('created_at');
        });
        Schema::table('adviser_feedback', fn (Blueprint $t) => $t->timestamp('acknowledged_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('adviser_feedback', fn (Blueprint $t) => $t->dropColumn('acknowledged_at'));
        Schema::dropIfExists('session_report_revisions');
        Schema::table('session_reports', fn (Blueprint $t) => $t->dropColumn(['session_result', 'follow_up_plan', 'summary_submitted_at', 'reflection_submitted_at', 'reassessment_requested_at', 'reassessment_reviewed_at']));
        Schema::table('counseling_sessions', fn (Blueprint $t) => $t->dropColumn(['match_status', 'documentation_status', 'completion_reason', 'documentation_notified_at']));
        Schema::table('readiness_checks', function (Blueprint $t) {
            $t->dropConstrainedForeignId('helper_schedule_id');
            $t->dropColumn(['form_version', 'skills_confirmed', 'expiry_notified_at']);
        });
        Schema::table('helpers', function (Blueprint $t) {
            $t->dropConstrainedForeignId('verified_by');
            $t->dropColumn(['verification_status', 'verified_at', 'verification_expires_at', 'training_verified', 'qualification_evidence', 'declared_specializations']);
        });
    }
};
