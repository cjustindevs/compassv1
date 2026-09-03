<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REFERRAL_STATUSES = ['pending_adviser', 'pending_consent', 'pending_professional', 'accepted', 'in_progress', 'declined', 'completed', 'closed', 'no_professional_available'];
    private const PREVIOUS_REFERRAL_STATUSES = ['pending_adviser', 'pending_professional', 'accepted', 'in_progress', 'declined', 'completed', 'closed'];

    public function up(): void
    {
        Schema::table('help_seekers', function (Blueprint $table) {
            $table->enum('current_risk_level', ['low', 'moderate', 'high', 'emergency'])->default('low')->after('gender');
            $table->timestamp('risk_last_updated')->nullable()->after('current_risk_level');
            $table->boolean('has_emergency')->default(false)->after('risk_last_updated');
            $table->timestamp('last_emergency_at')->nullable()->after('has_emergency');
        });

        Schema::table('counseling_sessions', function (Blueprint $table) {
            $table->boolean('requires_immediate_action')->default(false)->after('escalation_required');
            $table->boolean('requires_adviser_review')->default(false)->after('requires_immediate_action');
            $table->boolean('elevated_priority')->default(false)->after('requires_adviser_review');
            $table->boolean('requires_closer_monitoring')->default(false)->after('elevated_priority');
            $table->timestamp('emergency_triggered_at')->nullable()->after('requires_closer_monitoring');
            $table->timestamp('risk_updated_at')->nullable()->after('emergency_triggered_at');
            $table->text('risk_update_reason')->nullable()->after('risk_updated_at');
            $table->foreignId('risk_updated_by')->nullable()->after('risk_update_reason')->constrained('users', 'id')->nullOnDelete();
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->timestamp('reviewed_at')->nullable()->after('referral_date');
            $table->text('review_notes')->nullable()->after('reviewed_at');
            $table->timestamp('approved_at')->nullable()->after('review_notes');
            $table->timestamp('declined_at')->nullable()->after('approved_at');
            $table->timestamp('consent_requested_at')->nullable()->after('declined_at');
            $table->timestamp('consent_obtained_at')->nullable()->after('consent_requested_at');
            $table->timestamp('consent_declined_at')->nullable()->after('consent_obtained_at');
            $table->timestamp('professional_notified_at')->nullable()->after('consent_declined_at');
            $table->timestamp('accepted_at')->nullable()->after('professional_notified_at');
            $table->text('outcome')->nullable()->after('accepted_at');
            $table->boolean('follow_up_required')->default(false)->after('outcome');
            $table->text('follow_up_notes')->nullable()->after('follow_up_required');
            $table->timestamp('completed_at')->nullable()->after('follow_up_notes');
            $table->text('closure_notes')->nullable()->after('closed_date');
        });

        Schema::table('incident_reports', function (Blueprint $table) {
            $table->timestamp('reported_at')->nullable()->after('status');
            $table->boolean('is_confidential')->default(true)->after('reported_at');
            $table->foreignId('reviewed_by')->nullable()->after('is_confidential')->constrained('users', 'id')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_comments')->nullable()->after('reviewed_at');
            $table->timestamp('escalated_at')->nullable()->after('review_comments');
            $table->foreignId('escalated_to')->nullable()->after('escalated_at')->constrained('users', 'id')->nullOnDelete();
            $table->text('escalation_reason')->nullable()->after('escalated_to');
            $table->foreignId('resolved_by')->nullable()->after('resolved_at')->constrained('users', 'id')->nullOnDelete();
            $table->text('resolution_summary')->nullable()->after('resolved_by');
            $table->text('corrective_actions')->nullable()->after('resolution_summary');
            $table->timestamp('closed_at')->nullable()->after('corrective_actions');
            $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users', 'id')->nullOnDelete();
            $table->text('closure_notes')->nullable()->after('closed_by');
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('referrals', function (Blueprint $table) {
                $table->enum('status', self::REFERRAL_STATUSES)->default('pending_adviser')->change();
            });
        }

        $this->replaceReferralStatusConstraint(self::REFERRAL_STATUSES);
    }

    public function down(): void
    {
        Schema::table('incident_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('escalated_to');
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['reported_at', 'is_confidential', 'reviewed_at', 'review_comments', 'escalated_at', 'escalation_reason', 'resolution_summary', 'corrective_actions', 'closed_at', 'closure_notes']);
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn(['reviewed_at', 'review_notes', 'approved_at', 'declined_at', 'consent_requested_at', 'consent_obtained_at', 'consent_declined_at', 'professional_notified_at', 'accepted_at', 'outcome', 'follow_up_required', 'follow_up_notes', 'completed_at', 'closure_notes']);
        });

        Schema::table('counseling_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('risk_updated_by');
            $table->dropColumn(['requires_immediate_action', 'requires_adviser_review', 'elevated_priority', 'requires_closer_monitoring', 'emergency_triggered_at', 'risk_updated_at', 'risk_update_reason']);
        });

        Schema::table('help_seekers', function (Blueprint $table) {
            $table->dropColumn(['current_risk_level', 'risk_last_updated', 'has_emergency', 'last_emergency_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('referrals', function (Blueprint $table) {
                $table->enum('status', self::PREVIOUS_REFERRAL_STATUSES)->default('pending_adviser')->change();
            });
        }

        $this->replaceReferralStatusConstraint(self::PREVIOUS_REFERRAL_STATUSES);
    }

    private function replaceReferralStatusConstraint(array $statuses): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        $quoted = collect($statuses)->map(fn (string $status) => "'{$status}'")->implode(',');
        DB::statement('ALTER TABLE referrals DROP CONSTRAINT IF EXISTS referrals_status_check');
        DB::statement("ALTER TABLE referrals ADD CONSTRAINT referrals_status_check CHECK (status IN ({$quoted}))");
    }
};
