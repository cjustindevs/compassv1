<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('helpers', ['availability', 'is_ready', 'status'], 'helpers_availability_ready_status_idx');
        $this->addIndex('helpers', ['current_shift_sessions'], 'helpers_current_shift_sessions_idx');
        $this->addIndex('helpers', ['adviser_id'], 'helpers_adviser_id_idx');

        $this->addIndex('counseling_sessions', ['helper_id', 'session_status'], 'sessions_helper_status_idx');
        $this->addIndex('counseling_sessions', ['seeker_id', 'session_status'], 'sessions_seeker_status_idx');
        $this->addIndex('counseling_sessions', ['created_at'], 'sessions_created_at_idx');
        $this->addIndex('counseling_sessions', ['session_status', 'created_at'], 'sessions_status_created_at_idx');

        $this->addIndex('queue_requests', ['request_status', 'priority_level'], 'queue_status_priority_idx');
        $this->addIndex('queue_requests', ['seeker_id', 'request_status'], 'queue_seeker_status_idx');
        $this->addIndex('queue_requests', ['created_at'], 'queue_created_at_idx');

        $this->addIndex('readiness_checks', ['helper_id', 'is_active', 'valid_until'], 'readiness_helper_active_valid_idx');
        $this->addIndex('readiness_checks', ['assessment_date'], 'readiness_assessment_date_idx');
    }

    public function down(): void
    {
        foreach ([
            'helpers' => ['helpers_availability_ready_status_idx', 'helpers_current_shift_sessions_idx', 'helpers_adviser_id_idx'],
            'counseling_sessions' => ['sessions_helper_status_idx', 'sessions_seeker_status_idx', 'sessions_created_at_idx', 'sessions_status_created_at_idx'],
            'queue_requests' => ['queue_status_priority_idx', 'queue_seeker_status_idx', 'queue_created_at_idx'],
            'readiness_checks' => ['readiness_helper_active_valid_idx', 'readiness_assessment_date_idx'],
        ] as $table => $indexes) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($table, $indexes) {
                foreach ($indexes as $index) {
                    if ($this->hasIndex($table, $index)) {
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
