<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Duty is scheduled per day, not per shift. A date can carry as many helpers as
 * needed, but each helper holds a single duty record for that date and the day
 * carries no start or end time.
 *
 * This reverses the shift-based uniqueness added by
 * 2026_09_26_000004_allow_multiple_helper_shifts_per_day. Rows a helper gained
 * on a date they already held duty for are collapsed onto the earliest one, and
 * the recorded times are cleared because a duty day is now always the whole day.
 * The calendar_events.helper_schedule_id link is kept, since a duty row still
 * owns its calendar event.
 */
return new class extends Migration
{
    private const SHIFT_UNIQUE = 'helper_schedules_shift_unique';

    public function up(): void
    {
        // Collapse any extra rows a helper accumulated on a date, keeping the
        // earliest so a rollback does not drop the only record of their duty.
        // The foreign key cascades each removed row's duty calendar event.
        $keepPerHelperAndDay = DB::table('helper_schedules')
            ->select('helper_id', 'date', DB::raw('MIN(id) as keep_id'))
            ->groupBy('helper_id', 'date')
            ->pluck('keep_id');

        DB::table('helper_schedules')
            ->whereNotIn('id', $keepPerHelperAndDay)
            ->delete();

        // Duty is a whole day now, so a recorded start or end would wrongly
        // narrow when the helper counts as on duty.
        DB::table('helper_schedules')->update([
            'shift_start' => null,
            'shift_end' => null,
        ]);

        Schema::table('helper_schedules', function (Blueprint $table) {
            $table->dropUnique(self::SHIFT_UNIQUE);
        });

        Schema::table('helper_schedules', function (Blueprint $table) {
            $table->unique(['helper_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('helper_schedules', function (Blueprint $table) {
            $table->dropUnique(['helper_id', 'date']);
        });

        Schema::table('helper_schedules', function (Blueprint $table) {
            $table->unique(
                ['helper_id', 'date', 'shift_start', 'shift_end'],
                self::SHIFT_UNIQUE
            );
        });
    }
};
