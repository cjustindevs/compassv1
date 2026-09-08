<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerifyCompassSchema extends Command
{
    protected $signature = 'compass:verify-schema';
    protected $description = 'Verify COMPASS core tables and relationship constraints without modifying data';

    public function handle(): int
    {
        $relationships = [
            'help_seekers' => ['user_account_id'], 'helpers' => ['user_account_id', 'adviser_id'],
            'advisers' => ['user_account_id'], 'moderators' => ['user_account_id'],
            'psychology_professionals' => ['user_account_id'], 'system_administrators' => ['user_account_id'],
            'counseling_sessions' => ['seeker_id', 'helper_id', 'queue_request_id'],
            'queue_requests' => ['seeker_id', 'assigned_helper_id'], 'messages' => ['session_id'],
            'referrals' => ['session_id', 'helper_id', 'adviser_id', 'professional_id'],
            'helper_competency_history' => ['helper_id', 'adviser_id'],
            'readiness_checks' => ['helper_id'], 'helper_schedules' => ['helper_id', 'created_by', 'approved_by'],
            'adviser_feedback' => ['adviser_id'], 'consent_records' => ['seeker_id'],
            'screening_responses' => ['seeker_id', 'session_id'],
            'session_reports' => ['session_id'], 'help_seeker_evaluations' => ['session_id'],
        ];
        $errors = [];
        foreach ($relationships as $table => $columns) {
            if (! Schema::hasTable($table)) {
                $errors[] = "Missing table: {$table}";
                continue;
            }
            $foreignColumns = collect(Schema::getForeignKeys($table))->pluck('columns')->flatten()->all();
            foreach ($columns as $column) {
                if (! in_array($column, $foreignColumns, true)) {
                    $errors[] = "Missing foreign key: {$table}.{$column}";
                }
            }
        }
        foreach ($errors as $error) {
            $this->error($error);
        }
        if ($errors !== []) {
            return self::FAILURE;
        }
        $this->info('Verified '.count($relationships).' core tables and their relationship constraints on '.DB::getDriverName().'.');
        return self::SUCCESS;
    }
}
