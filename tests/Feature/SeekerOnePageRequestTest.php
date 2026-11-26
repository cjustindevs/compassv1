<?php

namespace Tests\Feature;

use App\Models\ConcernCategory;
use App\Models\HelpSeeker;
use App\Models\Session;
use App\Models\User;
use App\Services\CompactScreening;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Client feedback: log in straight to one request page, pick several concerns, chat opens by itself. */
class SeekerOnePageRequestTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\SeekerWorkflowFixtures;

    private function seeker(): User
    {
        $user = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'CalmFox'.$user->id, 'pseudo_id' => 'OP-'.$user->id, 'age' => 20, 'gender' => 'prefer-not-to-say']);
        $this->consentFixture($user);

        return $user;
    }

    private function request(array $overrides = []): array
    {
        $stress = ConcernCategory::firstOrCreate(['concern_name' => 'Stress'], ['is_active' => true]);
        $family = ConcernCategory::firstOrCreate(['concern_name' => 'Family Problems'], ['is_active' => true]);

        return array_replace(['screening_form_version' => CompactScreening::SINGLE_VERSION, 'concern_ids' => [$stress->id, $family->id],
            'safety_check' => 'no', 'support_mode' => 'chat', 'preferred_language' => 'English'], $overrides);
    }

    public function test_one_page_request_with_several_concerns_goes_straight_to_the_queue(): void
    {
        $this->actingAs($this->seeker())->post(route('request.screening.process'), $this->request())->assertRedirect(route('request.matching'));

        $session = Session::firstOrFail();
        $stress = ConcernCategory::where('concern_name', 'Stress')->value('id');
        $family = ConcernCategory::where('concern_name', 'Family Problems')->value('id');
        $this->assertSame('queued', $session->workflow_state);
        $this->assertSame($stress, (int) $session->concern_id, 'The first concern stays the main concern.');
        $this->assertSame([$stress, $family], array_map('intval', $session->concern_ids));
        $this->assertSame('Stress, Family Problems', $session->concern_label);
        $this->assertSame('chat', $session->session_type);
        $this->assertSame('English', $session->preferred_language);
        $this->assertNotNull($session->queue_request_id);
    }

    public function test_note_is_optional_on_the_one_page_request(): void
    {
        $this->actingAs($this->seeker())->post(route('request.screening.process'), $this->request(['description' => null]))
            ->assertSessionHasNoErrors()->assertRedirect(route('request.matching'));
    }

    public function test_other_concern_needs_a_short_label(): void
    {
        $other = ConcernCategory::firstOrCreate(['concern_name' => 'Others'], ['is_active' => true]);
        $base = $this->request();
        $this->actingAs($this->seeker())->post(route('request.screening.process'), $this->request(['concern_ids' => [$base['concern_ids'][0], $other->id]]))
            ->assertSessionHasErrors('custom_concern');
        $this->assertDatabaseCount('counseling_sessions', 0);
    }

    public function test_one_page_request_needs_a_concern_and_a_talk_mode(): void
    {
        $user = $this->seeker();
        $this->actingAs($user)->post(route('request.screening.process'), $this->request(['concern_ids' => []]))->assertSessionHasErrors('concern_id');
        $this->post(route('request.screening.process'), $this->request(['support_mode' => '']))->assertSessionHasErrors('support_mode');
        $this->assertDatabaseCount('counseling_sessions', 0);
    }

    public function test_safety_concern_still_opens_staff_review(): void
    {
        $this->actingAs($this->seeker())->post(route('request.screening.process'), $this->request(['safety_check' => 'yes']))
            ->assertRedirect(route('request.matching'));
        $this->assertTrue((bool) Session::firstOrFail()->requires_adviser_review);
    }

    public function test_seeker_logs_in_straight_to_the_request_page(): void
    {
        $user = $this->seeker();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('request.screening'));
    }

    public function test_request_page_shows_topics_as_choices_and_talk_mode(): void
    {
        ConcernCategory::firstOrCreate(['concern_name' => 'Stress'], ['is_active' => true]);
        $this->actingAs($this->seeker())->get(route('request.screening'))->assertOk()
            ->assertSee('name="concern_ids[]"', false)->assertSee('name="support_mode"', false)->assertSee('name="preferred_language"', false)
            ->assertSee("What's on your mind?", false)->assertDontSee('Step 1 of 3');
    }

    public function test_landing_shows_the_dial_a_friend_partnership_and_story(): void
    {
        $this->get('/')->assertOk()->assertSee('Partnership with Project Dial-a-Friend')->assertSee('images/pdaf/pdaf-seal-160', false)
            ->assertSee('About PDAF')->assertSee('A friend on the other end of the line, since 2021.');
    }
}
