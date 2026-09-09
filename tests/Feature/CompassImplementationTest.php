<?php

namespace Tests\Feature;

use App\Models\ConcernCategory;
use App\Models\HelpSeeker;
use App\Models\Session;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompassImplementationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pseudonymous_registration_consent_and_alias_login(): void
    {
        $this->get('/register')->assertOk()->assertSee('Create Your Account');
        $this->withSession(['registration_verified_until' => now()->addMinutes(10)->timestamp])->post(route('seeker.onboarding.store'), [
            'alias' => session('registration_alias'),
            'age' => 20, 'gender' => 'prefer-not-to-say', 'preferred_language' => 'Tagalog',
            'password' => 'StrongPass123!', 'password_confirmation' => 'StrongPass123!',
        ])->assertRedirect(route('seeker.consent'));
        $this->assertAuthenticated();
        $seeker = HelpSeeker::firstOrFail();
        $this->assertMatchesRegularExpression('/^[A-Z][a-z]+[A-Z][a-z]+[0-9]+$/', $seeker->generated_alias);
        $this->get(route('request.screening'))->assertRedirect(route('seeker.consent'));
        $this->post(route('seeker.consent.accept'), [
            'agree_privacy' => 1, 'agree_terms' => 1, 'agree_emergency' => 1, 'agree_consent' => 1,
        ])->assertRedirect(route('request.screening'));
        $this->assertDatabaseHas('consent_records', ['seeker_id' => $seeker->id, 'version' => '1.0', 'ip_address' => '127.0.0.1']);
        $this->post('/logout');
        $this->post('/login', ['email' => $seeker->generated_alias, 'password' => 'StrongPass123!'])->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_optional_description_and_private_risk_classification(): void
    {
        $user = $this->seeker();
        $this->actingAs($user)->get(route('request.screening'))->assertOk()->assertDontSee('Preliminary Risk Classification');
        $data = $this->screening();
        $this->post(route('request.screening.process'), $data)->assertRedirect(route('request.preferences'));
        $this->get(route('request.preferences'))->assertOk()->assertDontSee('Risk Classification:')->assertDontSee('name="additional_notes"', false)->assertDontSee('value="voice"', false);
        $this->assertDatabaseHas('screening_responses', ['is_active' => true, 'is_complete' => true, 'risk_level' => 'low']);
    }

    public function test_other_requires_description_and_all_screening_answers_are_required(): void
    {
        $this->actingAs($this->seeker());
        $data = $this->screening();
        $data['concern_id'] = ConcernCategory::firstOrCreate(['concern_name' => 'Others'])->id;
        unset($data['difficulty_coping']);
        $this->post(route('request.screening.process'), $data)->assertSessionHasErrors(['description', 'difficulty_coping']);
    }

    public function test_emergency_redirects_to_resources_without_queuing(): void
    {
        $this->actingAs($this->seeker());
        $data = $this->screening();
        $data['current_suicide_plan'] = $data['suicidal_thoughts'] = 1;
        $this->post(route('request.screening.process'), $data)->assertRedirect(route('emergency'));
        $this->assertDatabaseCount('queue_requests', 0);
    }

    public function test_expired_session_rejects_messages_and_caps_duration(): void
    {
        $user = $this->seeker();
        $session = Session::create(['seeker_id' => $user->helpSeeker->id, 'session_status' => 'active', 'start_time' => now()->subMinutes(91)]);
        $this->actingAs($user)->postJson(route('chat.send'), ['session_id' => $session->id, 'message' => 'Too late'])->assertStatus(409);
        $this->assertDatabaseHas('counseling_sessions', ['id' => $session->id, 'duration' => 90, 'auto_completed' => true]);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_feedback_requires_ended_owned_session_and_cannot_be_repeated(): void
    {
        $user = $this->seeker();
        $session = Session::create(['seeker_id' => $user->helpSeeker->id, 'session_status' => 'active', 'start_time' => now()]);
        $scores = array_fill_keys(['helpfulness_score', 'comfort_score', 'feeling_after_score', 'understood_score', 'reuse_score'], 10);
        $data = $scores + ['session_id' => $session->id];
        $this->actingAs($user)->postJson(route('session.evaluation.process'), $data)->assertStatus(409);
        $session->update(['session_status' => 'completed']);
        $this->post(route('session.evaluation.process'), $data)->assertRedirect(route('session.thank-you'));
        $this->assertDatabaseHas('help_seeker_evaluations', ['session_id' => $session->id, 'overall_score' => 10]);
        $this->postJson(route('session.evaluation.process'), $data)->assertStatus(409);
        $this->actingAs($this->seeker())->postJson(route('session.evaluation.process'), $data)->assertNotFound();
    }

    public function test_jwt_rejects_invalid_tokens_and_scopes_session_data(): void
    {
        $user = $this->seeker();
        Session::create(['seeker_id' => $user->helpSeeker->id, 'session_status' => 'active', 'risk_level' => 'high']);
        $other = $this->seeker();
        Session::create(['seeker_id' => $other->helpSeeker->id, 'session_status' => 'active']);
        $this->getJson('/api/sessions')->assertUnauthorized();
        $this->withToken('invalid')->getJson('/api/sessions')->assertUnauthorized();
        $token = app(JwtService::class)->issue($user);
        $this->withToken($token)->getJson('/api/sessions')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.risk_level');
    }

    public function test_adviser_pdf_export_and_date_validation(): void
    {
        $this->seed(\Database\Seeders\HelperModuleSeeder::class);
        $adviser = User::where('role', 'adviser')->firstOrFail();
        $response = $this->actingAs($adviser)->get(route('adviser.reports.export'));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->getJson(route('adviser.reports', ['from' => '2026-09-02', 'to' => '2026-09-01']))->assertUnprocessable();
    }

    private function seeker(): User
    {
        $user = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'CalmFox'.$user->id]);
        return $user;
    }

    public function test_matching_requires_schedule_and_reserves_pending_capacity(): void
    {
        $this->seed(\Database\Seeders\HelperModuleSeeder::class);
        $helper = \App\Models\Helper::firstOrFail();
        $helper->sessions()->update(['session_status' => 'completed']);
        $helper->update(['availability' => 'available', 'status' => 'available']);
        $this->assertFalse($helper->fresh()->isAvailable());
        \App\Models\HelperSchedule::create([
            'helper_id' => $helper->id, 'date' => today(), 'shift_start' => '00:00:00',
            'shift_end' => '23:59:59', 'created_by' => $helper->user_account_id,
        ]);
        $this->assertTrue($helper->fresh()->isAvailable());
        for ($i = 0; $i < 2; $i++) {
            Session::create(['seeker_id' => $this->seeker()->helpSeeker->id, 'helper_id' => $helper->id, 'session_status' => 'helper_assigned']);
        }
        $this->assertFalse($helper->fresh()->isAvailable());
    }

    public function test_referral_requires_seeker_decision_and_rejects_other_users(): void
    {
        $this->seed(\Database\Seeders\HelperModuleSeeder::class);
        $helper = \App\Models\Helper::firstOrFail();
        $seeker = $this->seeker();
        $session = Session::create(['seeker_id' => $seeker->helpSeeker->id, 'helper_id' => $helper->id, 'session_status' => 'active']);
        $this->actingAs($helper->user)->post(route('helper.session.referral', $session->id), [
            'referral_reason' => 'Additional professional support is recommended.', 'priority_level' => 'low',
            'help_seeker_consent' => true,
        ])->assertRedirect();
        $referral = $session->referrals()->firstOrFail();
        $this->assertSame('pending_adviser', $referral->status);
        $this->assertFalse($referral->help_seeker_consent);
        $this->actingAs($this->seeker())->postJson(route('referrals.consent', $referral), ['consent_given' => true])->assertForbidden();
        $this->actingAs($seeker)->postJson(route('referrals.consent', $referral), ['consent_given' => true])->assertStatus(409);
        $referral->update(['approved_at' => now(), 'status' => 'pending_consent']);
        $this->actingAs($seeker)->postJson(route('referrals.consent', $referral), ['consent_given' => true])->assertOk();
        $this->assertTrue($referral->fresh()->help_seeker_consent);
        $this->assertFalse($referral->fresh()->identity_disclosed);
    }

    private function screening(): array
    {
        return ['concern_id' => ConcernCategory::firstOrCreate(['concern_name' => 'Stress'])->id]
            + array_fill_keys(['current_suicide_plan', 'suicidal_thoughts', 'severe_distress', 'recurring_distress', 'difficulty_coping'], 0);
    }
}
