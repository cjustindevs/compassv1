<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ALL_STATUSES = ['pending_adviser', 'pending_consent', 'consent_requested', 'pending_professional', 'accepted', 'in_progress', 'declined', 'completed', 'closed', 'no_professional_available'];

    private const PREVIOUS_STATUSES = ['pending_adviser', 'pending_consent', 'pending_professional', 'accepted', 'in_progress', 'declined', 'completed', 'closed', 'no_professional_available'];

    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->text('referral_reason')->nullable()->change();
            $table->index(['session_id', 'status'], 'referrals_session_status_index');
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('referrals', function (Blueprint $table) {
                $table->enum('status', self::ALL_STATUSES)->default('pending_adviser')->change();
            });

            return;
        }

        DB::statement('ALTER TABLE referrals DROP CONSTRAINT IF EXISTS referrals_status_check');
        DB::statement("ALTER TABLE referrals ADD CONSTRAINT referrals_status_check CHECK (status IN ('pending_adviser','pending_consent','consent_requested','pending_professional','accepted','in_progress','declined','completed','closed','no_professional_available'))");
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->text('referral_reason')->nullable(false)->change();
            $table->dropIndex('referrals_session_status_index');
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('referrals', function (Blueprint $table) {
                $table->enum('status', self::PREVIOUS_STATUSES)->default('pending_adviser')->change();
            });

            return;
        }

        DB::statement('ALTER TABLE referrals DROP CONSTRAINT IF EXISTS referrals_status_check');
        DB::statement("ALTER TABLE referrals ADD CONSTRAINT referrals_status_check CHECK (status IN ('pending_adviser','pending_consent','pending_professional','accepted','in_progress','declined','completed','closed','no_professional_available'))");
    }
};