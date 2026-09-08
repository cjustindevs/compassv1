<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $sessionStatuses = [
        'scheduled',
        'active',
        'completed',
        'evaluated',
        'cancelled',
        'no_show',
        'screening_completed',
        'preferences_set',
        'waiting',
        'helper_assigned',
        'emergency',
        'pending_review',
    ];

    private array $referralStatuses = [
        'pending_adviser',
        'pending_professional',
        'pending_consent',
        'accepted',
        'in_progress',
        'declined',
        'completed',
        'closed',
        'no_professional_available',
    ];

    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            $this->syncPostgresChecks($this->sessionStatuses, 'counseling_sessions', 'session_status');
            $this->syncPostgresChecks($this->referralStatuses, 'referrals', 'status');
        }

        if ($driver === 'mysql') {
            $this->syncMysqlEnum($this->sessionStatuses, 'counseling_sessions', 'session_status', 'scheduled');
            $this->syncMysqlEnum($this->referralStatuses, 'referrals', 'status', 'pending_adviser');
        }
    }

    public function down(): void
    {
        // Do not narrow status constraints on rollback; existing data may use the
        // expanded states and narrowing would make rollback destructive.
    }

    private function syncPostgresChecks(array $values, string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $constraint = "{$table}_{$column}_check";
        $quoted = implode(', ', array_map(fn (string $value) => "'" . str_replace("'", "''", $value) . "'", $values));

        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK ({$column} IN ({$quoted}))");
    }

    private function syncMysqlEnum(array $values, string $table, string $column, string $default): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $quoted = implode(', ', array_map(fn (string $value) => "'" . str_replace("'", "''", $value) . "'", $values));

        DB::statement("ALTER TABLE {$table} MODIFY {$column} ENUM({$quoted}) NOT NULL DEFAULT '{$default}'");
    }
};
