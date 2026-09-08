<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('notifications', ['user_account_id', 'created_at'], 'notif_user_created_idx');
        $this->addIndex('notifications', ['user_account_id', 'read_at'], 'notif_user_read_idx');

        $this->addIndex('messages', ['session_id', 'created_at'], 'msg_session_created_idx');
        $this->addIndex('messages', ['sender_id'], 'msg_sender_idx');

        $this->addIndex('incident_reports', ['status'], 'incident_status_idx');
        $this->addIndex('incident_reports', ['risk_level'], 'incident_risk_idx');
        $this->addIndex('incident_reports', ['session_id'], 'incident_session_idx');
        $this->addIndex('incident_reports', ['created_at'], 'incident_created_idx');

        $this->addIndex('referrals', ['status'], 'referral_status_idx');
        $this->addIndex('referrals', ['helper_id', 'status'], 'referral_helper_status_idx');
        $this->addIndex('referrals', ['professional_id', 'status'], 'referral_professional_status_idx');
        $this->addIndex('referrals', ['adviser_id'], 'referral_adviser_idx');
        $this->addIndex('referrals', ['session_id'], 'referral_session_idx');

        $this->addIndex('session_reports', ['session_id'], 'report_session_idx');
        $this->addIndex('session_reports', ['adviser_reviewed'], 'report_reviewed_idx');
        $this->addIndex('session_reports', ['adviser_reviewed', 'created_at'], 'report_reviewed_created_idx');

        $this->addIndex('help_seeker_evaluations', ['session_id'], 'eval_session_idx');

        $this->addIndex('users', ['role'], 'users_role_idx');

        $this->addIndex('counseling_sessions', ['seeker_id', 'created_date'], 'sessions_seeker_created_idx');
        $this->addIndex('counseling_sessions', ['helper_id', 'created_date'], 'sessions_helper_created_idx');
        $this->addIndex('counseling_sessions', ['risk_level'], 'sessions_risk_idx');

        $this->addIndex('queue_requests', ['seeker_id', 'request_status'], 'queue_seeker_status_v2_idx');
        $this->addIndex('queue_requests', ['assigned_helper_id'], 'queue_helper_idx');

        $this->addIndex('helpers', ['adviser_id', 'status'], 'helpers_adviser_status_idx');
    }

    public function down(): void
    {
        foreach ([
            'notifications' => ['notif_user_created_idx', 'notif_user_read_idx'],
            'messages' => ['msg_session_created_idx', 'msg_sender_idx'],
            'incident_reports' => ['incident_status_idx', 'incident_risk_idx', 'incident_session_idx', 'incident_created_idx'],
            'referrals' => ['referral_status_idx', 'referral_helper_status_idx', 'referral_professional_status_idx', 'referral_adviser_idx', 'referral_session_idx'],
            'session_reports' => ['report_session_idx', 'report_reviewed_idx', 'report_reviewed_created_idx'],
            'help_seeker_evaluations' => ['eval_session_idx'],
            'users' => ['users_role_idx'],
            'counseling_sessions' => ['sessions_seeker_created_idx', 'sessions_helper_created_idx', 'sessions_risk_idx'],
            'queue_requests' => ['queue_seeker_status_v2_idx', 'queue_helper_idx'],
            'helpers' => ['helpers_adviser_status_idx'],
        ] as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $tableBlueprint) use ($table, $indexes) {
                foreach ($indexes as $index) {
                    $existing = collect(Schema::getIndexes($table))->pluck('name')->filter()->values()->all();
                    if (in_array($index, $existing)) {
                        $tableBlueprint->dropIndex($index);
                    }
                }
            });
        }
    }

    private function addIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table) || $this->hasIndex($table, $name)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $tableBlueprint) use ($columns, $name) {
            $tableBlueprint->index($columns, $name);
        });
    }

    private function hasIndex(string $table, string $name): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        return collect(Schema::getIndexes($table))->contains(fn (array $index) => ($index['name'] ?? null) === $name);
    }
};
