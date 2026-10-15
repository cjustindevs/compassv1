<?php

namespace Tests\Feature;

use App\Models\Adviser;
use App\Models\AuditLog;
use App\Models\EmergencyAlert;
use App\Models\Helper;
use App\Models\HelpSeeker;
use App\Models\HelpSeekerEvaluation;
use App\Models\PsychologyProfessional;
use App\Models\Referral;
use App\Models\ReferralAppointment;
use App\Models\ReferralCompletionReview;
use App\Models\Session;
use App\Models\User;
use App\Services\DefenseDemoData;
use App\Services\EvaluationInstrument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DefenseDemoDataTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->withoutVite();
        Mail::fake();
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(8, 0));
        $a = User::factory()->create(['role' => 'adviser']);
        $adviser = Adviser::create(['user_account_id' => $a->id, 'first_name' => 'Existing', 'last_name' => 'Adviser', 'email' => $a->email]);
        $h = User::factory()->create(['role' => 'helper']);
        $helper = Helper::create(['user_account_id' => $h->id, 'adviser_id' => $adviser->id, 'first_name' => 'Existing', 'last_name' => 'Helper', 'email' => $h->email, 'status' => 'offline', 'competency_score' => 3.5]);
        $s = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create(['user_account_id' => $s->id, 'generated_alias' => 'ExistingSeeker123']);
        $p = User::factory()->create(['role' => 'professional']);
        PsychologyProfessional::create(['user_account_id' => $p->id, 'first_name' => 'Existing', 'last_name' => 'Professional', 'email' => $p->email]);
        $session = Session::create(['seeker_id' => $seeker->id, 'helper_id' => $helper->id, 'session_status' => 'active', 'start_time' => now(), 'created_date' => now()]);

        return [$a, $h, $s, $p, $session];
    }

    private function snapshot(array $tables): array
    {
        return collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    public function test_additive_history_preserves_profiles_live_sessions_and_existing_records_without_messages_to_users(): void
    {
        [$a, $h, $s, $p, $live] = $this->fixture();
        $tables = ['users', 'helpers', 'advisers', 'help_seekers', 'psychology_professionals', 'helper_schedules', 'readiness_checks', 'helper_competency_history', 'notifications', 'jobs'];
        $before = $this->snapshot($tables);
        $original = $live->fresh()->getRawOriginal();
        $result = app(DefenseDemoData::class)->import();
        $this->assertTrue($result['created']);
        $this->assertSame($before, $this->snapshot($tables));
        $this->assertSame($original, $live->fresh()->getRawOriginal());
        $this->assertDatabaseCount('counseling_sessions', 41);
        $this->assertDatabaseCount('messages', 80);
        $this->assertDatabaseCount('session_reports', 40);
        $this->assertDatabaseCount('help_seeker_evaluations', 40);
        $this->assertDatabaseCount('referrals', 10);
        $this->assertDatabaseCount('referral_appointments', 10);
        $this->assertDatabaseCount('referral_completion_reviews', 10);
        $this->assertDatabaseCount('emergency_alerts', 4);
        $this->assertSame(0, EmergencyAlert::whereNotIn('status', ['resolved', 'closed'])->count());
        $this->assertSame(0, Referral::where('status', '!=', 'completed')->count());
        $this->assertSame(0, ReferralAppointment::where('status', '!=', 'completed')->count());
        $this->assertSame(0, DB::table('consent_records')->where('purpose', '!=', 'referral')->count());
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        $batch = app(DefenseDemoData::class)->existing();
        $this->assertCount(40, $batch->metadata['record_ids']['counseling_sessions']);
        $this->assertFalse($batch->metadata['existing_records_modified']);
        $this->assertFalse($batch->metadata['notifications_sent']);
        $this->assertStringNotContainsString(DefenseDemoData::LABEL, ReferralCompletionReview::first()->getRawOriginal('summary'));
        $this->assertStringNotContainsString(DefenseDemoData::LABEL, ReferralAppointment::first()->getRawOriginal('meeting_details'));
        $this->assertTrue(Referral::latest('completed_at')->first()->completed_at->isPast());
        foreach (HelpSeekerEvaluation::all() as $evaluation) {
            $scores = EvaluationInstrument::scores($evaluation->answers);
            $this->assertEquals(round(array_sum($scores) / count($scores), 1), $evaluation->overall_score);
        }
        $this->actingAs($p)->get(route('professional.dashboard'))->assertOk();
        $this->actingAs($a)->get(route('adviser.referrals'))->assertOk();
    }

    public function test_repeated_import_is_skipped_without_duplicates_or_profile_updates(): void
    {
        $this->fixture();
        $data = app(DefenseDemoData::class);
        $first = $data->import();
        $tables = array_keys($first['counts']);
        $before = $this->snapshot($tables);
        $second = $data->import();
        $this->assertFalse($second['created']);
        $this->assertSame($first['counts'], $second['counts']);
        $this->assertSame($before, $this->snapshot($tables));
        $this->assertSame(1, AuditLog::where('action', DefenseDemoData::ACTION)->count());
    }

    public function test_missing_profiles_fail_without_inserting_any_data(): void
    {
        $this->artisan('compass:seed-defense', ['--apply' => true])->expectsOutputToContain('No partial batch was committed')->assertFailed();
        $this->assertDatabaseCount('counseling_sessions', 0);
        $this->assertNull(app(DefenseDemoData::class)->existing());
    }

    public function test_preview_writes_nothing_and_apply_is_explicit(): void
    {
        $this->fixture();
        $this->artisan('compass:seed-defense')->expectsOutputToContain('Preview only')->assertSuccessful();
        $this->assertDatabaseCount('counseling_sessions', 1);
        $this->assertNull(app(DefenseDemoData::class)->existing());
        $this->artisan('compass:seed-defense', ['--apply' => true])->expectsOutputToContain('Defense demo batch added')->assertSuccessful();
        $this->artisan('compass:seed-defense', ['--apply' => true])->expectsOutputToContain('already exists; skipped')->assertSuccessful();
        $this->assertDatabaseCount('counseling_sessions', 41);
    }

    public function test_mid_import_database_failure_rolls_back_the_entire_batch_and_can_be_retried(): void
    {
        $this->fixture();
        DB::unprepared("CREATE TRIGGER defense_test_failure BEFORE INSERT ON referral_completion_reviews BEGIN SELECT RAISE(ABORT, 'test failure'); END");
        $this->artisan('compass:seed-defense', ['--apply' => true])->assertFailed();
        $this->assertDatabaseCount('counseling_sessions', 1);
        foreach (['referrals', 'messages', 'help_seeker_evaluations', 'session_reports', 'consent_records'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertNull(app(DefenseDemoData::class)->existing());
        DB::unprepared('DROP TRIGGER defense_test_failure');
        $this->assertTrue(app(DefenseDemoData::class)->import()['created']);
    }
}
