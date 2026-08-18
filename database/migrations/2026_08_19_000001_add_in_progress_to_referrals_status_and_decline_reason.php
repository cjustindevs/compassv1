<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ALL_STATUSES = ['pending_adviser', 'pending_professional', 'accepted', 'in_progress', 'declined', 'completed', 'closed'];

    private const PREVIOUS_STATUSES = ['pending_adviser', 'pending_professional', 'accepted', 'declined', 'completed', 'closed'];

    /**
     * Adds the 'in_progress' referral status (professional has started the
     * case) and a 'decline_reason' column so professionals can record why a
     * referral was declined.
     *
     * PostgreSQL stores Laravel 'enum' columns as varchar + CHECK constraints,
     * so the new status is added by replacing the constraint. SQLite keeps the
     * constraint inside the column definition, so the column must be rebuilt
     * via 'change'.
     */
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->text('decline_reason')->nullable();
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('referrals', function (Blueprint $table) {
                $table->enum('status', self::ALL_STATUSES)->default('pending_adviser')->change();
            });

            return;
        }

        DB::statement('ALTER TABLE referrals DROP CONSTRAINT IF EXISTS referrals_status_check');
        DB::statement("ALTER TABLE referrals ADD CONSTRAINT referrals_status_check CHECK (status IN ('pending_adviser','pending_professional','accepted','in_progress','declined','completed','closed'))");
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn('decline_reason');
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('referrals', function (Blueprint $table) {
                $table->enum('status', self::PREVIOUS_STATUSES)->default('pending_adviser')->change();
            });

            return;
        }

        DB::statement('ALTER TABLE referrals DROP CONSTRAINT IF EXISTS referrals_status_check');
        DB::statement("ALTER TABLE referrals ADD CONSTRAINT referrals_status_check CHECK (status IN ('pending_adviser','pending_professional','accepted','declined','completed','closed'))");
    }
};
