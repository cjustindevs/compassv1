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
    use \Tests\Concerns\SeekerWorkflowFixtures;

    public function test_pseudonymous_registration_consent_and_alias_login(): void
    {
        $this->get('/register')->assertOk()->assertSee('Create Your Account');
        $this->withSession(['registration_verified_until' => now()->addMinutes(10)->timestamp])->post(route('seeker.onboarding.store'), [
            'alias' => session('registration_alias'),
            'age' => 20, 'gender' => 'prefer-not-to-say', 'preferred_language' => 'Tagalog',
            'password' => 'StrongPass123!', 'password_confirmation' => 'StrongPass123!',
            'agree_privacy' => 1, 'agree_terms' => 1,
        ])->assertRedirect(route('request.screening'));
        $this->assertAuthenticated();
        $seeker = HelpSeeker::firstOrFail();
        $this->assertMatchesRegularExpression('/^[A-Z][a-z]+[A-Z][a-z]+[0-9]+$/', $seeker->generated_alias);
        $this->assertDatabaseHas('consent_records', ['seeker_id' => $seeker->id, 'version' => \App\Services\ConsentService::VERSION, 'ip_address' => '127.0.0.1']);
        $this->get(route('request.screening'))->assertOk()->assertSee('seekerConsentDialog');
        $this->post('/logout');
        $this->post('/login', ['email' => $seeker->generated_alias, 'password' => 'StrongPass123!'])->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_registration_and_settings_age_are_limited_to_13_through_60(): void
    {
        $this->get('/register')->assertOk();
        $alias = session('registration_alias');
        $base = [
            'gender' => 'prefer-not-to-say', 'preferred_language' => 'Tagalog',
            'password' => 'StrongPass123!', 'password_confirmation' => 'StrongPass123!',
            'agree_privacy' => 1, 'agree_terms' => 1,
        ];

        $this->withSession(['registration_verified_until' => now()->addMinutes(10)->timestamp])
            ->post(route('seeker.onboarding.store'), ['alias' => $alias, 'age' => 61] + $base)
            ->assertSessionHasErrors('age');
        $this->assertDatabaseCount('users', 0);

        $this->withSession(['registration_verified_until' => now()->addMinutes(10)->timestamp])
            ->post(route('seeker.onboarding.store'), ['alias' => $alias, 'age' => 60] + $base)
            ->assertRedirect(route('request.screening'));

        $seeker = HelpSeeker::firstOrFail();
        $this->assertSame(60, $seeker->age);

        $this->actingAs($seeker->user)
            ->patch(route('settings.account.update'), ['gender' => 'male', 'age' => 61])
            ->assertSessionHasErrors('age');
        $this->actingAs($seeker->user)
            ->patch(route('settings.account.update'), ['gender' => 'male', 'age' => 13])
            ->assertSessionHasNoErrors();
        $this->assertSame(13, $seeker->fresh()->age);
    }

    public function test_settings_pages_and_profiles_work_for_every_staff_role(): void
    {
        $moderatorUser = User::factory()->create(['role' => 'moderator', 'is_active' => true]);
        \App\Models\Moderator::create([
            'user_account_id' => $moderatorUser->id, 'first_name' => 'Mod', 'last_name' => 'Erator',
            'email' => 'mod@compass.test', 'assigned_shift' => 'Morning',
        ]);
        $adviserUser = User::factory()->create(['role' => 'adviser', 'is_active' => true]);
        \App\Models\Adviser::create([
            'user_account_id' => $adviserUser->id, 'first_name' => 'Adv', 'last_name' => 'Iser',
            'email' => 'adv@compass.test',
        ]);

        $this->actingAs($moderatorUser)->get(route('moderator.settings'))->assertOk();
        $this->actingAs($moderatorUser)->put(route('moderator.settings.profile'), [
            'name' => 'Herald 05', 'email' => 'herald@compass.test',
            'first_name' => 'Mod', 'last_name' => 'Erator', 'assigned_shift' => 'Evening',
        ])->assertRedirect();
        $this->assertSame('herald@compass.test', $moderatorUser->fresh()->email);

        $this->actingAs($adviserUser)->get(route('adviser.settings'))->assertOk();
        $this->actingAs($adviserUser)->put(route('adviser.settings.profile'), [
            'name' => 'Adv Iser', 'email' => 'adv@compass.test',
            'first_name' => 'Adv', 'last_name' => 'Iser',
        ])->assertRedirect();
        $this->assertSame('Adv Iser', $adviserUser->fresh()->name);

        $this->actingAs($moderatorUser)->put(route('moderator.settings.password'), [
            'current_password' => 'wrong-password',
            'new_password' => 'NewSecret123!', 'new_password_confirmation' => 'NewSecret123!',
        ])->assertSessionHasErrors('current_password');

        $seeker = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        \App\Models\HelpSeeker::create(['user_account_id' => $seeker->id, 'generated_alias' => 'Star_42', 'age' => 21]);

        foreach (['settings.account', 'settings.preferences', 'settings.privacy', 'settings.appearance'] as $route) {
            $this->actingAs($seeker)->get(route($route))->assertOk();
        }

        $this->actingAs($seeker)->patch(route('settings.preferences.update'), [
            'preferred_language' => 'English/Tagalog',
            'preferred_communication_mode' => 'chat',
            'session_duration_preference' => '30',
        ])->assertSessionHasNoErrors();

        $this->actingAs($seeker)->patch(route('settings.privacy.update'), ['clear_sessions' => 1])
            ->assertRedirect()
            ->assertSessionHasErrors('clear_sessions');
    }

    public function test_optional_description_and_private_risk_classification(): void
    {
        $user = $this->seeker();
        $this->actingAs($user)->get(route('request.screening'))->assertOk()->assertDontSee('Preliminary Risk Classification');
        $data = $this->screening();
        $this->post(route('request.screening.process'), $data)->assertRedirect(route('request.concern'));
        $other = ConcernCategory::where('concern_name','Others')->value('id');
        $this->post(route('request.concern.process'), ['concern_id'=>$other])->assertSessionHasErrors('description');
        $this->post(route('request.concern.process'), ['concern_id'=>$other, 'description'=>'A concern outside the listed categories.'])->assertSessionHasNoErrors()->assertRedirect(route('request.preferences'));
        $this->get(route('request.preferences'))->assertOk()->assertDontSee('Risk Classification:')->assertDontSee('name="additional_notes"', false)->assertDontSee('value="voice"', false)->assertSee('id="preferencesForm"', false)->assertSee('name="preferred_language"', false)->assertDontSee(route('request.cancel', 1), false);
        $this->assertDatabaseHas('screening_responses', ['is_active' => true, 'is_complete' => true, 'risk_level' => 'low']);
    }

    public function test_all_screening_answers_are_required(): void
    {
        $this->actingAs($this->seeker());
        $data = $this->screening();
        $data['concern_id'] = ConcernCategory::firstOrCreate(['concern_name' => 'Others'])->id;
        unset($data['difficulty_coping']);
        $this->post(route('request.screening.process'), $data)->assertSessionHasErrors(['difficulty_coping']);
    }

    public function test_emergency_opens_review_and_temporary_support_queue(): void
    {
        $this->actingAs($this->seeker());
        $data = $this->screening();
        $data['immediate_intent'] = 'yes';
        $this->post(route('request.screening.process'), $data)->assertRedirect(route('request.matching'));
        $this->assertDatabaseHas('queue_requests', ['priority_level'=>'emergency','request_status'=>'waiting']);
        $this->assertDatabaseCount('emergency_alerts', 1);
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
        $scores = $this->evaluationAnswers();
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
        $this->consentFixture($user); $this->approvedScreeningFixture();
        return $user;
    }

    public function test_matching_requires_schedule_and_reserves_pending_capacity(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-16 19:00','Asia/Manila')->utc());
        $this->seed(\Database\Seeders\HelperModuleSeeder::class);
        $helper = \App\Models\Helper::firstOrFail();
        $this->verifiedHelperFixture($helper);
        $helper->sessions()->update(['session_status' => 'completed','start_time'=>now()->subDay()]);
        $helper->update(['availability' => 'available', 'status' => 'available']);
        $this->assertFalse($helper->fresh()->isAvailable());
        \App\Models\HelperSchedule::create([
            'helper_id' => $helper->id, 'date' => today(), 'shift_start' => '00:00:00',
            'shift_end' => '23:59:59', 'created_by' => $helper->user_account_id,
        ]);
        $this->assertTrue($helper->fresh()->isAvailable());
        for ($i = 0; $i < 1; $i++) {
            Session::create(['seeker_id' => $this->seeker()->helpSeeker->id, 'helper_id' => $helper->id, 'session_status' => 'helper_assigned']);
        }
        $this->assertFalse($helper->fresh()->isAvailable());
    }

    public function test_referral_requires_seeker_decision_and_rejects_other_users(): void
    {
        $this->seed(\Database\Seeders\HelperModuleSeeder::class);
        $helper = \App\Models\Helper::firstOrFail();
        $this->verifiedHelperFixture($helper);
        $seeker = $this->seeker();
        $session = Session::create(['seeker_id' => $seeker->helpSeeker->id, 'helper_id' => $helper->id, 'risk_level'=>'low','session_status' => 'active','helper_accepted_at'=>now(),'start_time'=>now()]);
        $this->actingAs($helper->user)->post(route('helper.session.referral.consent', ['id' => $session->id]), [
            'summary' => 'Additional professional support is recommended.',
        ])->assertSessionHasNoErrors();
        $referral = $session->referrals()->firstOrFail();
        $this->assertSame('pending_adviser', $referral->status);
        $this->assertFalse($referral->help_seeker_consent);
        $this->actingAs($this->seeker())->postJson(route('referrals.consent-request', $referral), ['accepted' => true])->assertForbidden();
        $this->actingAs($helper->adviser->user)->postJson(route('referrals.review',$referral),['approved'=>true,'notes'=>'Reviewed recommendation.'])->assertOk();
        $this->actingAs($seeker)->postJson(route('referrals.consent-request', $referral), ['accepted' => true])->assertOk()->assertJson(['status' => 'pending_professional']);
        $this->assertTrue($referral->fresh()->help_seeker_consent);
        $this->assertFalse($referral->fresh()->identity_disclosed);
    }

    private function screening(): array
    {
        return $this->screeningAnswers();
    }
}
