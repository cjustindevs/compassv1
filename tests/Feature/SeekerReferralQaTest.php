<?php

namespace Tests\Feature;

use App\Models\Adviser;
use App\Models\Helper;
use App\Models\HelpSeeker;
use App\Models\Notification;
use App\Models\ProfessionalNote;
use App\Models\PsychologyProfessional;
use App\Models\Referral;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** QA round 2, Seeker module: withdraw notifications, wait note, one history per Seeker. */
class SeekerReferralQaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function fixture(string $status = 'in_progress'): array
    {
        $au = User::factory()->create(['role' => 'adviser']);
        $a = Adviser::create(['user_account_id' => $au->id, 'first_name' => 'Qa', 'last_name' => 'Adviser', 'email' => $au->email]);
        $hu = User::factory()->create(['role' => 'helper']);
        $h = Helper::create(['user_account_id' => $hu->id, 'adviser_id' => $a->id, 'first_name' => 'Qa', 'last_name' => 'Helper', 'email' => $hu->email]);
        $su = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create(['user_account_id' => $su->id, 'generated_alias' => 'QaAlias'.$su->id, 'pseudo_id' => 'PS-QA-'.$su->id]);
        $pu = User::factory()->create(['role' => 'professional']);
        $p = PsychologyProfessional::create(['user_account_id' => $pu->id, 'first_name' => 'Qa', 'last_name' => 'Professional', 'email' => $pu->email, 'is_available' => true]);
        $make = function (string $status, $date) use ($seeker, $h, $a, $p) {
            $session = Session::create(['seeker_id' => $seeker->id, 'helper_id' => $h->id, 'review_adviser_id' => $a->id, 'session_status' => 'completed', 'session_type' => 'chat', 'start_time' => $date, 'helper_accepted_at' => $date, 'created_date' => $date]);

            return Referral::create(['session_id' => $session->id, 'helper_id' => $h->id, 'adviser_id' => $a->id, 'professional_id' => $p->id, 'status' => $status,
                'approved_at' => $date, 'help_seeker_consent' => true, 'consent_obtained_at' => $date, 'accepted_at' => $date, 'priority_level' => 'low',
                'referral_reason' => 'Synthetic recommendation.', 'referral_date' => $date]);
        };

        return [$su, $pu, $make($status, now()->subDays(10)), $make, $p];
    }

    public function test_seeker_and_professional_are_notified_when_the_seeker_withdraws_a_referral(): void
    {
        [$su, $pu, $referral] = $this->fixture();
        $referral->update(['help_seeker_consent' => false, 'status' => Referral::STATUS_CLOSED, 'closed_date' => now(), 'closure_notes' => 'Help Seeker withdrew referral consent.']);

        $this->assertDatabaseHas('notifications', ['user_account_id' => $su->id, 'title' => 'Referral withdrawn']);
        $this->assertDatabaseHas('notifications', ['user_account_id' => $pu->id, 'title' => 'Referral withdrawn by the Seeker']);
    }

    public function test_seeker_is_notified_when_staff_close_a_referral(): void
    {
        [$su, $pu, $referral] = $this->fixture();
        $referral->update(['status' => Referral::STATUS_CLOSED, 'closed_date' => now()]);

        $this->assertDatabaseHas('notifications', ['user_account_id' => $su->id, 'title' => 'Referral closed']);
        $this->assertDatabaseMissing('notifications', ['user_account_id' => $pu->id, 'title' => 'Referral withdrawn by the Seeker']);
        $this->assertSame(1, Notification::where('user_account_id', $su->id)->count());
    }

    public function test_seeker_sees_a_wait_note_and_emergency_numbers_while_the_professional_reviews(): void
    {
        [$su, , $referral] = $this->fixture(Referral::STATUS_PENDING_PROFESSIONAL);

        $this->actingAs($su)->get(route('seeker.referrals'))->assertOk()
            ->assertSee('Please wait for the Professional')
            ->assertSee('href="tel:911"', false)->assertSee('href="tel:1553"', false);
    }

    public function test_professional_sees_one_history_across_follow_up_referrals_for_the_same_seeker(): void
    {
        [, $pu, $first, $make] = $this->fixture();
        $followUp = $make('in_progress', now()->subDay());
        ProfessionalNote::create(['referral_id' => $first->id, 'session_id' => $first->session_id, 'professional_id' => $first->professional_id, 'intervention_type' => 'assessment', 'notes' => 'First intake note.']);

        $this->actingAs($pu)->get(route('professional.cases.show', $followUp->case_reference))->assertOk()
            ->assertSee('Case history with this Seeker')
            ->assertSee('Referral #'.$first->id)->assertSee('Follow-up 1')
            ->assertSee('First intake note.');
    }
}
