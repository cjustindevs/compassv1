<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * PostgreSQL stores Laravel 'enum' columns as varchar + CHECK constraints,
     * so the new flow statuses are added by replacing the constraint.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE counseling_sessions DROP CONSTRAINT IF EXISTS counseling_sessions_session_status_check');
        DB::statement("ALTER TABLE counseling_sessions ADD CONSTRAINT counseling_sessions_session_status_check CHECK (session_status IN ('scheduled','active','completed','cancelled','no_show','screening_completed','preferences_set','waiting','helper_assigned'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE counseling_sessions DROP CONSTRAINT IF EXISTS counseling_sessions_session_status_check');
        DB::statement("ALTER TABLE counseling_sessions ADD CONSTRAINT counseling_sessions_session_status_check CHECK (session_status IN ('scheduled','active','completed','cancelled','no_show'))");
    }
};