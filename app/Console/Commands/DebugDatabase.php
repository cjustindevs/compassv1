<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DebugDatabase extends Command
{
    protected $signature = 'db:debug {--table= : Specific table to check}';

    protected $description = 'Debug COMPASS database schema and find missing columns';

    public function handle(): int
    {
        $this->info('COMPASS Database Debug Tool');
        $this->line('===========================');

        $tables = $this->option('table')
            ? [(string) $this->option('table')]
            : array_keys($this->requirements());

        foreach ($tables as $table) {
            $this->checkTable($table);
        }

        return self::SUCCESS;
    }

    protected function checkTable(string $table): void
    {
        $this->newLine();
        $this->line('Table: '.$table);
        $this->line(str_repeat('-', 40));

        if (! Schema::hasTable($table)) {
            $this->error("Table '{$table}' does not exist.");
            return;
        }

        $columns = Schema::getColumnListing($table);
        $this->info(count($columns).' columns found');

        $missing = array_values(array_diff($this->requirements()[$table] ?? [], $columns));

        if ($missing) {
            $this->warn('Missing columns: '.implode(', ', $missing));
        } else {
            $this->info('All required columns present');
        }

        $this->checkKnownAliases($table, $columns);
        $this->checkColumnTypes($table, $columns);
    }

    protected function requirements(): array
    {
        return [
            'helpers' => [
                'id', 'user_account_id', 'adviser_id', 'first_name', 'last_name', 'email',
                'status', 'availability', 'available_since', 'break_started_at',
                'is_ready', 'last_readiness_at', 'competency_level', 'competency_score',
                'competency_risk_level', 'max_concurrent_sessions', 'active_sessions_count',
                'total_sessions_handled', 'current_shift_sessions', 'feedback_count',
                'avg_rating', 'languages', 'specialties', 'default_shift_start',
                'default_shift_end', 'is_under_review', 'review_reason',
            ],
            'users' => [
                'id', 'name', 'email', 'password', 'role', 'is_active',
            ],
            'counseling_sessions' => [
                'id', 'seeker_id', 'helper_id', 'queue_request_id', 'moderator_id', 'concern_id',
                'session_type', 'risk_level', 'concern_category', 'session_status',
                'scheduled_start', 'start_time', 'end_time', 'duration',
                'voice_recording_consent', 'voice_consent_obtained',
                'pre_session_brief_expires_at', 'match_method', 'matched_by',
                'matching_details', 'completion_status',
            ],
            'queue_requests' => [
                'id', 'seeker_id', 'moderator_id', 'assigned_helper_id', 'request_date',
                'scheduled_date', 'request_status', 'priority_level', 'preferred_session_type',
                'queue_position', 'estimated_wait', 'aging_priority_increases',
                'last_priority_increase_at', 'max_wait_reached', 'matched_date', 'voice_consent',
            ],
            'messages' => [
                'id', 'session_id', 'sender_id', 'sender', 'message_text', 'transcript',
                'is_transcript', 'transcript_generated_at', 'sent_datetime', 'is_reviewed',
            ],
            'readiness_checks' => [
                'id', 'helper_id', 'assessment_result', 'emotionally_ready',
                'willing_to_listen', 'stress_level', 'assessment_date', 'valid_until',
                'is_active', 'breathing_exercise',
            ],
            'helper_schedules' => [
                'id', 'helper_id', 'date', 'shift_start', 'shift_end', 'is_active',
            ],
            'helper_specialties' => [
                'id', 'helper_id', 'category', 'proficiency_level',
            ],
            'helper_availability_logs' => [
                'id', 'helper_id', 'previous_status', 'new_status', 'changed_at', 'reason', 'changed_by',
            ],
            'referrals' => [
                'id', 'session_id', 'helper_id', 'adviser_id', 'professional_id', 'status',
            ],
            'helper_competency_history' => [
                'id', 'helper_id', 'evaluation_date', 'overall_score',
            ],
        ];
    }

    protected function checkKnownAliases(string $table, array $columns): void
    {
        $aliases = [
            'helpers' => ['user_id' => 'user_account_id', 'available' => 'availability', 'ready' => 'is_ready', 'helper_status' => 'status'],
            'counseling_sessions' => ['started_at' => 'start_time', 'ended_at' => 'end_time'],
            'queue_requests' => ['status' => 'request_status', 'priority' => 'priority_level', 'requested_at' => 'request_date', 'matched_at' => 'matched_date'],
            'messages' => ['message' => 'message_text', 'sent_at' => 'sent_datetime', 'sender_type' => 'sender'],
        ];

        foreach ($aliases[$table] ?? [] as $wrong => $correct) {
            if (in_array($wrong, $columns, true)) {
                $this->warn("Alias column '{$wrong}' found; app expects '{$correct}'.");
            }
        }
    }

    protected function checkColumnTypes(string $table, array $columns): void
    {
        foreach (['languages', 'specialties', 'matching_details'] as $jsonColumn) {
            if (in_array($jsonColumn, $columns, true) && Schema::getColumnType($table, $jsonColumn) !== 'json') {
                $this->warn("Column '{$jsonColumn}' is type '".Schema::getColumnType($table, $jsonColumn)."'; expected 'json'.");
            }
        }
    }
}
