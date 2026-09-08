<?php

namespace Tests\Feature;

use App\Models\{Adviser, EmergencyAlert, HelpSeeker, Helper, PsychologyProfessional, Referral, Session, User};
use App\Services\IdentityVaultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IdentityVaultTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.identity_vault' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
            'identity_vault.key' => 'base64:' . base64_encode(str_repeat('v', 32))]);
        DB::purge('identity_vault');
        $this->artisan('migrate', ['--database' => 'identity_vault', '--path' => 'database/migrations/identity_vault', '--force' => true])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        DB::purge('identity_vault');
        parent::tearDown();
    }

    private function referral(): Referral
    {
        $seeker = HelpSeeker::create(['user_account_id' => User::factory()->create(['role' => 'seeker'])->id,
            'generated_alias' => 'Calm-Star', 'pseudo_id' => 'PS-TEST-123']);
        $adviser = Adviser::create(['user_account_id' => User::factory()->create(['role' => 'adviser'])->id,
            'first_name' => 'Adviser', 'last_name' => 'Test', 'email' => 'adviser@example.com']);
        $helper = Helper::create(['user_account_id' => User::factory()->create(['role' => 'helper'])->id,
            'first_name' => 'Helper', 'last_name' => 'Test', 'email' => 'helper@example.com', 'adviser_id' => $adviser->id]);
        $professional = PsychologyProfessional::create(['user_account_id' => User::factory()->create(['role' => 'professional'])->id,
            'first_name' => 'Professional', 'last_name' => 'Test', 'email' => 'professional@example.com']);
        $session = Session::create(['seeker_id' => $seeker->id, 'helper_id' => $helper->id,
            'session_status' => 'active', 'session_type' => 'chat', 'risk_level' => 'moderate', 'created_date' => now()]);
        return Referral::create(['session_id' => $session->id, 'helper_id' => $helper->id, 'adviser_id' => $adviser->id,
            'professional_id' => $professional->id, 'status' => 'pending_professional', 'approved_at' => now(),
            'help_seeker_consent' => true, 'consent_obtained_at' => now(), 'referral_reason' => 'Support', 'referral_date' => now(), 'priority_level' => 'moderate']);
    }

    public function test_availability_and_storage_self_test_use_no_real_identity(): void
    {
        $this->assertTrue(app(IdentityVaultService::class)->isAvailable());
        $this->artisan('identity-vault:verify')->assertSuccessful();
        $this->assertDatabaseCount('idv_identities', 0, 'identity_vault');
        $this->assertDatabaseHas('idv_access_logs', ['action' => 'storage_self_test', 'access_status' => 'success'], 'identity_vault');
    }

    public function test_legacy_migration_verifies_ciphertext_before_scrubbing_source_and_is_idempotent(): void
    {
        $referral = $this->referral();
        $seeker = $referral->session->seeker;
        $seeker->user->update(['name' => 'Legacy Private Name', 'email' => 'legacy@example.com']);
        DB::table('identity_vault')->insert(['seeker_id' => $seeker->id, 'real_name' => 'Legacy Private Name', 'email' => 'legacy@example.com']);
        $this->artisan('identity-vault:migrate-legacy')->assertSuccessful();
        $this->assertDatabaseCount('identity_vault', 0);
        $this->assertSame('Calm-Star', $seeker->user->fresh()->name);
        $this->assertSame('calm-star@compass.local', $seeker->user->fresh()->email);
        $row = DB::connection('identity_vault')->table('idv_identities')->first();
        $this->assertNotSame('Legacy Private Name', $row->real_name);
        $this->artisan('identity-vault:migrate-legacy')->assertSuccessful();
        $this->assertDatabaseCount('idv_identities', 1, 'identity_vault');
    }

    public function test_legacy_migration_keeps_source_when_vault_audit_fails(): void
    {
        $referral = $this->referral();
        $seeker = $referral->session->seeker;
        $seeker->user->update(['name' => 'Retain Private Name', 'email' => 'retain@example.com']);
        DB::connection('identity_vault')->getSchemaBuilder()->drop('idv_access_logs');
        $this->artisan('identity-vault:migrate-legacy')->assertFailed();
        $this->assertSame('retain@example.com', $seeker->user->fresh()->email);
    }

    private function store(Referral $referral): void
    {
        $this->actingAs($referral->session->seeker->user)->postJson(route('identity.store', $referral), [
            'real_name' => 'Private Test Name', 'phone_number' => '+639171234567', 'email' => 'private@example.com',
        ])->assertOk();
    }

    public function test_identity_is_encrypted_separately_and_released_only_to_assigned_professional(): void
    {
        $referral = $this->referral();
        $this->store($referral);
        $row = DB::connection('identity_vault')->table('idv_identities')->first();
        $this->assertNotSame('Private Test Name', $row->real_name);
        $this->assertStringNotContainsString('private@example.com', json_encode($row));
        $this->assertDatabaseCount('identity_vault', 0);
        $this->actingAs($referral->professional->user)->get(route('identity.show', $referral))->assertForbidden();
        $this->actingAs($referral->adviser->user)->post(route('identity.release', $referral))->assertRedirect();
        $this->actingAs($referral->professional->user)->get(route('identity.show', $referral))
            ->assertOk()->assertSee('Private Test Name')->assertHeader('Cache-Control', 'no-store, private');
        $this->post(route('identity.acknowledge', $referral))->assertRedirect();
        $this->assertTrue((bool) DB::connection('identity_vault')->table('idv_release_records')->value('recipient_acknowledged'));
    }

    public function test_admin_helper_moderator_and_unassigned_professional_are_denied_and_logged(): void
    {
        $referral = $this->referral();
        $this->store($referral);
        foreach (['admin', 'helper', 'moderator', 'professional', 'seeker', 'adviser'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('identity.show', $referral))->assertForbidden();
        }
        $this->assertGreaterThanOrEqual(6, DB::connection('identity_vault')->table('idv_access_logs')->where('access_status', 'denied')->count());
    }

    public function test_replacing_identity_requires_a_new_release_even_for_the_same_referral(): void
    {
        $referral = $this->referral(); $this->store($referral);
        $this->actingAs($referral->adviser->user)->post(route('identity.release', $referral))->assertRedirect();
        $this->actingAs($referral->session->seeker->user)->postJson(route('identity.store', $referral), [
            'real_name' => 'Updated Private Name', 'phone_number' => '+639171234567',
        ])->assertOk();
        $this->assertFalse($referral->fresh()->identity_disclosed);
        $this->actingAs($referral->professional->user)->get(route('identity.show', $referral))->assertForbidden();
        $this->actingAs($referral->adviser->user)->post(route('identity.release', $referral))->assertRedirect();
        $this->actingAs($referral->professional->user)->get(route('identity.show', $referral))->assertOk()->assertSee('Updated Private Name');
    }

    public function test_consent_and_approval_are_required_and_plaintext_is_not_flashed_on_validation_error(): void
    {
        $referral = $this->referral();
        $referral->update(['help_seeker_consent' => false]);
        $this->actingAs($referral->session->seeker->user)->postJson(route('identity.store', $referral), [
            'real_name' => 'Private Test Name', 'phone_number' => '+639171234567',
        ])->assertForbidden();
        $this->post(route('identity.store', $referral), ['real_name' => 'Private Test Name'])->assertUnprocessable()->assertSessionMissing('_old_input');
        $this->assertSame(0, DB::connection('identity_vault')->table('idv_identities')->count());
    }

    public function test_audit_failure_fails_closed_without_returning_plaintext(): void
    {
        $referral = $this->referral(); $this->store($referral);
        $this->actingAs($referral->adviser->user)->post(route('identity.release', $referral))->assertRedirect();
        DB::connection('identity_vault')->getSchemaBuilder()->drop('idv_access_logs');
        $this->actingAs($referral->professional->user)->getJson(route('identity.show', $referral))
            ->assertStatus(503)->assertDontSee('Private Test Name');
    }

    public function test_expired_identity_is_unreadable_and_purged(): void
    {
        $referral = $this->referral(); $this->store($referral);
        $this->actingAs($referral->adviser->user)->post(route('identity.release', $referral))->assertRedirect();
        $this->travel(366)->days();
        $this->actingAs($referral->professional->user)->getJson(route('identity.show', $referral))->assertStatus(503)->assertDontSee('Private Test Name');
        auth()->logout();
        $this->artisan('identity-vault:purge-expired')->assertSuccessful();
        $row = DB::connection('identity_vault')->table('idv_identities')->first();
        $this->assertNull($row->real_name);
        $this->assertFalse((bool) $row->is_active);
    }

    public function test_emergency_requires_designated_responder_and_active_life_threat(): void
    {
        $referral = $this->referral(); $this->store($referral);
        $session = $referral->session;
        $session->update(['risk_level' => 'emergency', 'requires_immediate_action' => true]);
        EmergencyAlert::create(['session_id' => $session->id, 'seeker_id' => $session->seeker_id,
            'alert_type' => 'safety_threat', 'trigger_reason' => 'Immediate threat', 'status' => 'referred']);
        $url = route('identity.emergency', $session);
        $reason = ['reason' => 'Immediate threat to life requires emergency contact.'];
        $this->actingAs($referral->helper->user)->postJson($url, $reason)->assertForbidden();
        $this->actingAs($referral->professional->user)->postJson($url, $reason)->assertForbidden();
        config(['identity_vault.emergency_responder_ids' => [$referral->professional->user_account_id]]);
        $this->post($url, $reason)->assertOk()->assertSee('Private Test Name');
        $this->assertDatabaseHas('idv_release_records', ['release_reason' => 'life_threatening_emergency', 'consent_obtained' => false, 'reviewed_at' => null], 'identity_vault');
    }
}
