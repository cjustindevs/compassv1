<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAdviserWorld;
use Tests\TestCase;

class AdviserRedesignPagesTest extends TestCase
{
    use BuildsAdviserWorld;
    use RefreshDatabase;

    public function test_dashboard_has_header_and_one_bell_slot(): void
    {
        $adviser = $this->adviser();

        $html = $this->actingAs($adviser->user)->get(route('adviser.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-page-header', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame(1, substr_count($html, 'data-notification-slot'));
        $this->assertSame(0, substr_count($html, 'data-page-slot'));
    }

    public function test_attention_strip_lists_only_waiting_work(): void
    {
        $adviser = $this->adviser();

        $this->actingAs($adviser->user)->get(route('adviser.dashboard'))
            ->assertOk()->assertDontSee('Needs your attention');

        $this->documentedReport($adviser);
        $this->activeEmergency($adviser);

        $this->actingAs($adviser->user)->get(route('adviser.dashboard'))
            ->assertOk()
            ->assertSee('Needs your attention')
            ->assertSee('Evaluations due')
            ->assertSee('href="'.route('adviser.evaluations').'"', false)
            ->assertSee('Emergencies open')
            ->assertSee('href="'.route('adviser.emergencies').'"', false)
            ->assertDontSee('Referrals to review');
    }

    public function test_attention_counts_match_sidebar_badges(): void
    {
        $adviser = $this->adviser();
        $this->documentedReport($adviser);
        $this->documentedReport($adviser);

        $html = $this->actingAs($adviser->user)->get(route('adviser.dashboard'))->assertOk()->getContent();

        preg_match('/id="evalBadge"[^>]*>\s*(\d+)/', $html, $badge);
        preg_match('/ui-attention-count">(\d+)<\/strong>\s*<span class="ui-attention-label">Evaluations due/', $html, $strip);

        $this->assertNotEmpty($badge);
        $this->assertNotEmpty($strip);
        $this->assertSame('2', $strip[1]);
        $this->assertSame($badge[1], $strip[1]);
    }

    public function test_pending_evaluations_matches_reference(): void
    {
        $adviser = $this->adviser();
        $high = $this->documentedReport($adviser, 'completed', 'high');
        $low = $this->documentedReport($adviser);

        $this->actingAs($adviser->user)->get(route('adviser.evaluations'))
            ->assertOk()
            ->assertSee('ui-kpi--red', false)
            ->assertSee('ui-kpi-icon', false)
            ->assertSee('Evaluation Process')
            ->assertSee('Awaiting review')
            ->assertSee('2 sessions')
            ->assertSee('ui-eval-row--high', false)
            ->assertSee('Awaiting your evaluation')
            ->assertSee(route('adviser.evaluate', $high->id), false)
            ->assertSee(route('adviser.session.show', $low->session_id), false)
            ->assertDontSee('⋮');

        $html = $this->actingAs($adviser->user)->get(route('adviser.evaluations'))->getContent();
        $this->assertSame(2, substr_count($html, 'data-eval-row'));
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_pending_evaluations_empty_state_keeps_held_and_undocumented_notes(): void
    {
        $adviser = $this->adviser();
        $this->documentedReport($adviser, 'active');
        $report = $this->documentedReport($adviser);
        $report->update(['session_summary' => null, 'personal_reflection' => null]);

        $this->actingAs($adviser->user)->get(route('adviser.evaluations'))
            ->assertOk()
            ->assertSee('All caught up!')
            ->assertSee('withheld')
            ->assertSee('until the session is completed or evaluated')
            ->assertSee('awaiting')
            ->assertSee('session documentation from the Helper')
            ->assertDontSee('data-eval-row', false);
    }

    public function test_pending_evaluations_keeps_history_links_and_archive_action(): void
    {
        $adviser = $this->adviser();
        $report = $this->documentedReport($adviser);
        $report->update(['adviser_reviewed' => true]);

        $this->actingAs($adviser->user)->get(route('adviser.evaluations'))
            ->assertOk()
            ->assertSee('Recent reviews')
            ->assertSee('Archived reviews')
            ->assertSee('history=archived', false)
            ->assertSee('Recently Completed')
            ->assertSee('Quality supervision leads to quality support.');
    }

    public function test_evaluate_form_has_sections_save_bar_and_unchanged_contract(): void
    {
        $adviser = $this->adviser();
        $report = $this->documentedReport($adviser);

        $response = $this->actingAs($adviser->user)->get(route('adviser.evaluate', $report->id))
            ->assertOk()
            ->assertSee('Back to Pending Evaluations')
            ->assertSee('ui-save-bar', false)
            ->assertSee('action="'.route('adviser.evaluate.store', $report->id).'"', false)
            ->assertSee('id="evaluationDeclaration"', false)
            ->assertSee('openConversation', false)
            ->assertSee('Session record')
            ->assertSee('Your evaluation');

        foreach (['active_listening', 'empathy', 'ethical_practices', 'referral_accuracy', 'respect_professionalism'] as $name) {
            $response->assertSee('data-target="'.$name.'"', false)->assertSee('name="'.$name.'"', false);
        }
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_evaluate_validation_errors_show_next_to_fields_and_open_the_save_bar(): void
    {
        $adviser = $this->adviser();
        $report = $this->documentedReport($adviser);

        $this->actingAs($adviser->user)->from(route('adviser.evaluate', $report->id))
            ->post(route('adviser.evaluate.store', $report->id), [])
            ->assertRedirect(route('adviser.evaluate', $report->id));

        $this->actingAs($adviser->user)->get(route('adviser.evaluate', $report->id))
            ->assertOk()
            ->assertSee('ui-save-bar is-visible', false)
            ->assertSee('ui-field-error', false);
    }

    public function test_pending_evaluations_has_client_side_controls_and_no_server_filter(): void
    {
        $adviser = $this->adviser();
        $this->documentedReport($adviser, 'completed', 'high');
        $this->documentedReport($adviser);

        $html = $this->actingAs($adviser->user)->get(route('adviser.evaluations'))
            ->assertOk()
            ->assertSee('id="evalSearch"', false)
            ->assertSee('id="evalRisk"', false)
            ->assertSee('id="evalSort"', false)
            ->assertSee('id="evalNoMatch"', false)
            ->assertSee('eval-list-filter.js', false)
            ->assertSee('data-risk="high"', false)
            ->assertSee('data-time=', false)
            ->assertSee('data-search=', false)
            ->getContent();
        $this->assertSame(2, substr_count($html, 'data-eval-row'));

        $filtered = $this->actingAs($adviser->user)->get(route('adviser.evaluations', ['q' => 'x', 'risk' => 'high']))->getContent();
        $this->assertSame(2, substr_count($filtered, 'data-eval-row'));
    }

    private function pendingScreening(\App\Models\Adviser $adviser): \App\Models\Session
    {
        $owner = \App\Models\User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        $seeker = \App\Models\HelpSeeker::create(['user_account_id' => $owner->id, 'generated_alias' => 'Alias'.$owner->id, 'age' => 20, 'gender' => 'prefer-not-to-say']);

        return \App\Models\Session::create(['seeker_id' => $seeker->id, 'session_type' => 'chat', 'session_status' => 'pending_review',
            'workflow_state' => 'adviser_review_required', 'requires_adviser_review' => true, 'risk_level' => null,
            'review_adviser_id' => $adviser->id, 'created_date' => now()]);
    }

    public function test_screenings_page_header_filters_and_forms(): void
    {
        $adviser = $this->adviser();
        $session = $this->pendingScreening($adviser);

        $html = $this->actingAs($adviser->user)->get(route('adviser.screenings'))->assertOk()
            ->assertSee(route('adviser.screenings.review', $session), false)->getContent();

        $this->assertSame(1, substr_count($html, 'data-page-header'));
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame(3, substr_count($html, 'data-filter="'));
        $this->assertStringContainsString('ui-card', $html);
    }

    public function test_screening_conversation_has_back_link_and_summary(): void
    {
        $adviser = $this->adviser();
        $session = $this->pendingScreening($adviser);
        $session->update(['helper_id' => $this->helperFor($adviser)->id, 'session_status' => 'completed']);

        $this->actingAs($adviser->user)
            ->withSession(['adviser_transcript.'.$session->id => ['actor_id' => $adviser->user->id, 'purpose' => 'referral_review', 'expires_at' => now()->addMinutes(5)->timestamp]])
            ->get(route('adviser.screenings.conversation', $session))
            ->assertOk()->assertSee('ui-back-link', false)->assertSee('Back to Screening Reviews')
            ->assertSee('data-page-header', false);
    }

    public function test_manage_helpers_uses_shared_blocks(): void
    {
        $adviser = $this->adviser();
        $this->helperFor($adviser);

        $html = $this->actingAs($adviser->user)->get(route('adviser.helpers'))->assertOk()
            ->assertSee(route('adviser.helpers.create'), false)->assertSee('Add a Helper')
            ->assertSee(route('adviser.helpers.export'), false)->getContent();
        $this->assertStringContainsString('ui-kpis', $html);
        $this->assertStringContainsString('ui-table--stack', $html);
        $this->assertStringContainsString('data-label="Helper"', $html);
        $this->assertSame(1, substr_count($html, 'data-page-header'));
        $this->assertSame(1, substr_count($html, '<h1'));

        $this->actingAs($adviser->user)->get(route('adviser.helpers', ['status' => 'offline']))->assertOk()
            ->assertSee('ui-chip', false)->assertSee('Remove filter Status: offline');
    }

    public function test_helper_detail_and_matching_have_back_link_and_summary(): void
    {
        $adviser = $this->adviser();
        $helper = $this->helperFor($adviser);

        $detail = $this->actingAs($adviser->user)->get(route('adviser.helper.show', ['id' => $helper->id]))->assertOk()
            ->assertSee('ui-back-link', false)->assertSee('Back to Manage Helpers')
            ->assertSee(route('adviser.helpers.reassign'), false)->getContent();
        $this->assertSame(1, substr_count($detail, 'data-page-header'));
        $this->assertSame(1, substr_count($detail, '<h1'));
        $this->assertStringContainsString('ui-card', $detail);

        $match = $this->actingAs($adviser->user)->get(route('adviser.helper.matching', ['id' => $helper->id]))->assertOk()
            ->assertSee('ui-back-link', false)->assertSee('Back to Manage Helpers')
            ->assertSee(route('adviser.helper.competency.update', $helper->id), false)->getContent();
        $this->assertSame(1, substr_count($match, '<h1'));
        $this->assertStringContainsString('ui-table--stack', $match);
    }

    public function test_add_helper_form_keeps_ids_and_action(): void
    {
        $adviser = $this->adviser();

        $this->actingAs($adviser->user)->get(route('adviser.helpers.create'))->assertOk()
            ->assertSee(route('adviser.helpers.store'), false)
            ->assertSee('id="hcEmail"', false)->assertSee('id="hcFirst"', false)->assertSee('id="hcEvidence"', false)
            ->assertSee('id="hcAccount"', false)->assertSee('id="hcProof"', false)->assertSee('id="hcAfter"', false)
            ->assertSee('ui-back-link', false);
    }

    private function pendingReferral(\App\Models\Adviser $adviser, string $priority = 'emergency'): \App\Models\Referral
    {
        $helper = $this->helperFor($adviser);
        $seekerUser = \App\Models\User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        $seeker = \App\Models\HelpSeeker::create(['user_account_id' => $seekerUser->id, 'generated_alias' => 'RefSeeker'.$seekerUser->id]);
        $session = \App\Models\Session::create(['seeker_id' => $seeker->id, 'helper_id' => $helper->id, 'review_adviser_id' => $adviser->id, 'session_status' => 'active', 'session_type' => 'chat', 'start_time' => now(), 'created_date' => now()]);

        return \App\Models\Referral::create(['session_id' => $session->id, 'helper_id' => $helper->id, 'adviser_id' => $adviser->id,
            'status' => 'pending_adviser', 'priority_level' => $priority, 'referral_reason' => 'Needs follow-up.', 'referral_date' => now()]);
    }

    public function test_referral_queue_uses_shared_blocks_and_keeps_modals(): void
    {
        $adviser = $this->adviser();
        $this->pendingReferral($adviser);

        $html = $this->actingAs($adviser->user)->get(route('adviser.referrals'))->assertOk()->getContent();

        $this->assertStringContainsString('ui-kpi--red', $html);
        $this->assertStringContainsString('ui-table--stack', $html);
        $this->assertStringContainsString('id="approveModal"', $html);
        $this->assertStringContainsString('id="approveForm"', $html);
        $this->assertStringContainsString('id="rejectModal"', $html);
        $this->assertStringContainsString('id="rejectForm"', $html);
        $this->assertSame(1, substr_count($html, 'data-page-header'));
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_referral_detail_has_back_link_summary_and_forms(): void
    {
        $adviser = $this->adviser();
        $referral = $this->pendingReferral($adviser, 'low');

        $html = $this->actingAs($adviser->user)->get(route('adviser.referral.show', $referral->case_reference))->assertOk()
            ->assertSee('ui-back-link', false)->assertSee('Back to Referral Queue')
            ->assertSee('id="approveForm"', false)->assertSee('id="reviseForm"', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame(1, substr_count($html, 'data-page-header'));
        $this->assertMatchesRegularExpression('#<form[^>]*id="approveForm"[^>]*action="[^"]*/approve"#', $html);
        $this->assertStringContainsString('ui-card', $html);
    }
}
