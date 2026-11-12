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
}
