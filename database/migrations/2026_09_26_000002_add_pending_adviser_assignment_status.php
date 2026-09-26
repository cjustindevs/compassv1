<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ALL_STATUSES = ['pending_adviser', 'pending_adviser_assignment', 'pending_consent', 'consent_requested', 'pending_professional', 'accepted', 'in_progress', 'declined', 'completed', 'closed', 'no_professional_available'];

    private const PREVIOUS_STATUSES = ['pending_adviser', 'pending_consent', 'consent_requested', 'pending_professional', 'accepted', 'in_progress', 'declined', 'completed', 'closed', 'no_professional_available'];

    private const PENDING_ASSIGNMENT = 'pending_adviser_assignment';

    private const CONSTRAINT_SQL = "CHECK (status IN ('pending_adviser','pending_adviser_assignment','pending_consent','consent_requested','pending_professional','accepted','in_progress','declined','completed','closed','no_professional_available'))";

    private const PREVIOUS_CONSTRAINT_SQL = "CHECK (status IN ('pending_adviser','pending_consent','consent_requested','pending_professional','accepted','in_progress','declined','completed','closed','no_professional_available'))";

    public function up(): void
    {
        if (in_array(Schema::getConnection()->getDriverName(), ['sqlite', 'mysql', 'mariadb'], true)) {
            Schema::table('referrals', function (Blueprint $table) {
                $table->enum('status', self::ALL_STATUSES)->default('pending_adviser')->change();
            });

            return;
        }

        DB::statement('ALTER TABLE referrals DROP CONSTRAINT IF EXISTS referrals_status_check');
        DB::statement('ALTER TABLE referrals ADD CONSTRAINT referrals_status_check '.self::CONSTRAINT_SQL);
    }

    public function down(): void
    {
        // Unassigned referrals predate no Adviser assignment tracking, so they
        // fold back into the generic awaiting-review state rather than fail.
        DB::table('referrals')
            ->where('status', self::PENDING_ASSIGNMENT)
            ->update(['status' => 'pending_adviser']);

        if (in_array(Schema::getConnection()->getDriverName(), ['sqlite', 'mysql', 'mariadb'], true)) {
            Schema::table('referrals', function (Blueprint $table) {
                $table->enum('status', self::PREVIOUS_STATUSES)->default('pending_adviser')->change();
            });

            return;
        }

        DB::statement('ALTER TABLE referrals DROP CONSTRAINT IF EXISTS referrals_status_check');
        DB::statement('ALTER TABLE referrals ADD CONSTRAINT referrals_status_check '.self::PREVIOUS_CONSTRAINT_SQL);
    }
};
