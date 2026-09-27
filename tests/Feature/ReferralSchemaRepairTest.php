<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferralSchemaRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_restores_missing_structures_and_preserves_history_on_repeat(): void
    {
        Schema::table('referrals', fn ($table) => $table->dropColumn('recommendation_form'));
        Schema::drop('supervision_record_versions');

        $migration = require database_path('migrations/2026_09_27_000002_repair_referral_submission_schema.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('referrals', 'recommendation_form'));
        $this->assertTrue(Schema::hasTable('supervision_record_versions'));
        DB::table('supervision_record_versions')->insert([
            'record_type' => 'referral', 'record_id' => 1, 'version' => 1,
            'reason' => 'Test history', 'snapshot' => '{}', 'created_at' => now(),
        ]);

        $migration->up();
        $migration->down();
        $this->assertDatabaseCount('supervision_record_versions', 1);
        $this->assertTrue(Schema::hasColumn('referrals', 'recommendation_form'));
    }
}
