<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Duty scheduling was date-based. helper_schedules carried a unique index on
 * (helper_id, date), so a helper could hold at most one duty record per day no
 * matter what the shift times were, and the shift times themselves were
 * optional. A split shift, or a second shift on the same day, could not be
 * recorded at all, and both the Adviser and Moderator forms silently
 * overwrote the existing row.
 *
 * Duty is now shift-based. A helper may hold several non-overlapping shifts on
 * the same date, and uniqueness applies to an individual shift rather than to
 * the day. Overlap is rejected in HelperShiftService because it has to account
 * for an overnight shift spilling into the following day, which no unique
 * index can express.
 */
return new class extends Migration
{
    private const SHIFT_UNIQUE = 'helper_schedules_shift_unique';

    public function up(): void
    {
        // Duty calendar events were previously matched back to a shift by
        // parsing the "Duty: {name}" title, which cannot identify one shift
        // once a helper holds several on the same day.
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->foreignId('helper_schedule_id')
                ->nullable()
                ->after('event_type')
                ->constrained('helper_schedules')
                ->cascadeOnDelete();
        });

        Schema::table('helper_schedules', function (Blueprint $table) {
            $table->dropUnique(['helper_id', 'date']);
        });

        Schema::table('helper_schedules', function (Blueprint $table) {
            // Stops the same shift being entered twice. Rows predating this
            // migration may have null times, and nulls are distinct in a
            // unique index on both SQLite and PostgreSQL, so legacy all-day
            // rows are left to the overlap check instead.
            $table->unique(
                ['helper_id', 'date', 'shift_start', 'shift_end'],
                self::SHIFT_UNIQUE
            );
        });
    }

    public function down(): void
    {
        // Reinstating the one-per-day rule requires collapsing the extra
        // shifts a helper may now hold. The earliest row for each helper and
        // date is kept so a rollback does not silently drop the only record of
        // a helper's duty.
        $duplicates = DB::table('helper_schedules')
            ->select('helper_id', 'date', DB::raw('MIN(id) as keep_id'))
            ->groupBy('helper_id', 'date')
            ->pluck('keep_id');

        DB::table('helper_schedules')
            ->whereNotIn('id', $duplicates)
            ->delete();

        Schema::table('helper_schedules', function (Blueprint $table) {
            $table->dropUnique(self::SHIFT_UNIQUE);
        });

        Schema::table('helper_schedules', function (Blueprint $table) {
            $table->unique(['helper_id', 'date']);
        });

        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('helper_schedule_id');
        });
    }
};
