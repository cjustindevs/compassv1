<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PostgreSQL stores Laravel 'enum' columns as varchar + CHECK constraints,
     * so the new flow statuses are added by replacing the constraint.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            Schema::table('counseling_sessions', function (Blueprint $table) {
                $table->enum('session_status', [
                    'scheduled',
                    'active',
                    'completed',
                    'cancelled',
                    'no_show',
                    'screening_completed',
                    'preferences_set',
                    'waiting',
                    'helper_assigned',
                ])->default('scheduled')->change();
            });

            return;
        }

        DB::statement('ALTER TABLE counseling_sessions DROP CONSTRAINT IF EXISTS counseling_sessions_session_status_check');
        DB::statement("ALTER TABLE counseling_sessions ADD CONSTRAINT counseling_sessions_session_status_check CHECK (session_status IN ('scheduled','active','completed','cancelled','no_show','screening_completed','preferences_set','waiting','helper_assigned'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            Schema::table('counseling_sessions', function (Blueprint $table) {
                $table->enum('session_status', [
                    'scheduled',
                    'active',
                    'completed',
                    'cancelled',
                    'no_show',
                ])->default('scheduled')->change();
            });

            return;
        }

        DB::statement('ALTER TABLE counseling_sessions DROP CONSTRAINT IF EXISTS counseling_sessions_session_status_check');
        DB::statement("ALTER TABLE counseling_sessions ADD CONSTRAINT counseling_sessions_session_status_check CHECK (session_status IN ('scheduled','active','completed','cancelled','no_show'))");
    }
};
