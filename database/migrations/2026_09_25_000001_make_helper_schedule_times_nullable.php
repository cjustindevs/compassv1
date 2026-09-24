<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Duty scheduling is date-based: a HelperSchedule now marks a helper as
     * on duty for the whole day of the recorded date. Shift times become
     * optional informational metadata (backfilled to the approved 6:00 PM -
     * 11:00 PM operating window for historical planned-hours reporting).
     */
    public function up(): void
    {
        Schema::table('helper_schedules', function (Blueprint $table) {
            $table->time('shift_start')->nullable()->change();
            $table->time('shift_end')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('helper_schedules')->whereNull('shift_start')->update(['shift_start' => '18:00']);
        DB::table('helper_schedules')->whereNull('shift_end')->update(['shift_end' => '23:00']);

        Schema::table('helper_schedules', function (Blueprint $table) {
            $table->time('shift_start')->nullable(false)->change();
            $table->time('shift_end')->nullable(false)->change();
        });
    }
};