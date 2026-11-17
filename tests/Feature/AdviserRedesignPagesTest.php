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

    public function test_original_wording_is_kept_on_redesigned_pages(): void
    {
        $adviser = $this->adviser();
        $this->actingAs($adviser->user);

        $this->get(route('adviser.reports'))->assertOk()->assertSee('Export case CSV')->assertSee('Export case PDF');
        $this->get(route('adviser.helpers.create'))->assertOk()->assertSee('Proof of eligibility')->assertDontSee('Qualification evidence');
        $this->get(route('adviser.helpers'))->assertOk()->assertSee('Advanced / Expert competency');
        $this->get(route('adviser.emergencies'))->assertOk()->assertSee('>Search</button>', false)->assertDontSee('>Apply</button>', false);
        $this->get(route('adviser.dashboard'))->assertOk()->assertDontSee('data-label="Action">', false);
    }

    public function test_emergencies_list_uses_kpis_tabs_and_search(): void
    {
        $adviser = $this->adviser();
        $this->activeEmergency($adviser);

        $html = $this->actingAs($adviser->user)->get(route('adviser.emergencies', ['q' => 'Safe']))->assertOk()->getContent();

        $this->assertStringContainsString('ui-kpi--red', $html);
        $this->assertStringContainsString('id="emergencySearch"', $html);
        $this->assertStringContainsString('ui-table--stack', $html);
        $this->assertStringContainsString('ui-chip', $html);
        $this->assertMatchesRegularExpression('#<a[^>]*href="[^"]*tab=active"[^>]*>\s*Active#', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame(1, substr_count($html, 'data-page-header'));
    }

    public function test_emergency_detail_has_back_link_summary_and_keeps_forms(): void
    {
        $adviser = $this->adviser();
        $alert = $this->activeEmergency($adviser);

        $html = $this->actingAs($adviser->user)->get(route('adviser.emergencies.show', $alert->id))->assertOk()
            ->assertSee('ui-back-link', false)->assertSee('Back to Emergencies')
            ->assertSee('id="resolutionNotes"', false)->assertSee('id="handoffReason"', false)
            ->assertSee('id="reviewAction"', false)->assertSee('id="coordinatorId"', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame(1, substr_count($html, 'data-page-header'));
        $this->assertStringContainsString(route('adviser.emergencies.resolve', $alert->id), $html);
        $this->assertStringContainsString(route('adviser.emergencies.coordinator', $alert->id), $html);
        $this->assertStringContainsString('data-focus-label="Resolve emergency"', $html);
    }

    public function test_live_sessions_page_and_watch_page(): void
    {
        $adviser = $this->adviser();
        $helper = $this->helperFor($adviser);
        $seekerUser = \App\Models\User::factory()->create(['role' => 'seeker']);
        $seeker = \App\Models\HelpSeeker::create(['user_account_id' => $seekerUser->id, 'generated_alias' => 'QuietOwl'.$seekerUser->id, 'pseudo_id' => 'PS-T11-'.$seekerUser->id]);
        $session = \App\Models\Session::create(['seeker_id' => $seeker->id, 'helper_id' => $helper->id, 'session_status' => 'active', 'session_type' => 'chat',
            'start_time' => now()->subMinutes(5), 'helper_accepted_at' => now()->subMinutes(5), 'created_date' => now()]);

        $list = $this->actingAs($adviser->user)->get(route('adviser.live.index'))->assertOk()->assertSee('Watch live')->getContent();
        $this->assertSame(1, substr_count($list, 'data-page-header'));
        $this->assertSame(1, substr_count($list, '<h1'));

        $watch = $this->get(route('adviser.live.show', $session->id))->assertOk()
            ->assertSee('ui-back-link', false)->assertSee('data-live-note-form', false)
            ->assertSee('id="lwNote"', false)->assertSee('id="lwCoach"', false)->assertSee('id="lwTranscript"', false)
            ->getContent();
        $this->assertSame(1, substr_count($watch, 'data-page-header'));
        $this->assertSame(1, substr_count($watch, '<h1'));
        $this->assertStringContainsString(route('adviser.live.feed', $session->id), $watch);
        $this->assertStringContainsString(route('adviser.live.notes', $session->id), $watch);
    }

    public function test_transcript_pages_have_header_and_back_link(): void
    {
        $adviser = $this->adviser();
        $report = $this->documentedReport($adviser);

        $list = $this->actingAs($adviser->user)->get(route('adviser.transcripts'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($list, 'data-page-header'));
        $this->assertSame(1, substr_count($list, '<h1'));

        $detail = $this->get(route('adviser.session.show', $report->session_id))->assertOk()->assertSee('ui-back-link', false)->getContent();
        $this->assertSame(1, substr_count($detail, '<h1'));
    }

    public function test_reports_has_filter_bar_chips_and_kpis(): void
    {
        $adviser = $this->adviser();

        $html = $this->actingAs($adviser->user)->get(route('adviser.reports', ['tab' => 'safety', 'priority' => 'high']))->assertOk()
            ->assertSee('Priority: high')->assertSee('ui-filter-bar', false)->assertSee('ui-kpis', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame(1, substr_count($html, 'data-page-header'));
        $this->assertStringContainsString('name="priority"', $html);
        $this->assertStringContainsString('tab=cases', $html);
    }

    public function test_calendar_resources_schedule_keep_hooks(): void
    {
        $adviser = $this->adviser();
        $this->helperFor($adviser);

        $calendar = $this->actingAs($adviser->user)->get(route('adviser.calendar'))->assertOk()
            ->assertSee('id="calGrid"', false)->assertSee('id="openEventModal"', false)
            ->assertSee('id="dayPanel"', false)->assertSee('id="eventModal"', false)->getContent();
        $this->assertSame(1, substr_count($calendar, 'data-page-header'));
        $this->assertSame(1, substr_count($calendar, '<h1'));

        $resources = $this->get(route('adviser.resources'))->assertOk()
            ->assertSee('id="openAddModal"', false)->assertSee('id="resourceForm"', false)->assertSee('id="categorySelect"', false)->getContent();
        $this->assertSame(1, substr_count($resources, 'data-page-header'));
        $this->assertSame(1, substr_count($resources, '<h1'));

        // adviser.schedule is served by ModeratorScheduleController (moderator.schedules view); adviser/schedule.blade.php is unrouted.
        $schedule = $this->get(route('adviser.schedule'))->assertOk()
            ->assertSee('id="dutyForm"', false)
            ->assertSee('data-helper-workload', false)->assertSee('data-workload-body', false)->getContent();
        $this->assertSame(1, substr_count($schedule, 'data-page-header'));
    }

    public function test_settings_forms_have_save_bars_and_keep_actions(): void
    {
        $adviser = $this->adviser();

        $html = $this->actingAs($adviser->user)->get(route('adviser.settings'))->assertOk()->getContent();

        foreach (['profile', 'password', 'notifications'] as $form) {
            $this->assertMatchesRegularExpression('#<form[^>]*action="'.preg_quote(route('adviser.settings.'.$form), '#').'"[^>]*>.*?data-save-bar.*?</form>#s', $html);
        }
        foreach (['profile', 'password', 'appearance', 'notifications'] as $tab) {
            $this->assertStringContainsString('data-tab="'.$tab.'"', $html);
            $this->assertStringContainsString('id="section-'.$tab.'"', $html);
        }
        $this->assertStringContainsString('ui-save-bar', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame(1, substr_count($html, 'data-page-header'));
    }

    public function test_notifications_and_concerns_use_page_header(): void
    {
        $adviser = $this->adviser();

        foreach ([route('adviser.notifications'), route('adviser.notifications', ['history' => 'archived']), route('concerns.manage')] as $url) {
            $html = $this->actingAs($adviser->user)->get($url)->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, 'data-page-header'), $url);
            $this->assertSame(1, substr_count($html, '<h1'), $url);
        }

        $admin = \App\Models\User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $html = $this->actingAs($admin)->get(route('concerns.manage'))->assertOk()
            ->assertSee('id="addConcernTitle"', false)->assertSee('id="categoryStatus"', false)->getContent();
        $this->assertSame(1, substr_count($html, 'data-page-header'));
    }

    public function test_dashboard_session_documentation_shows_the_five_most_recent(): void
    {
        $adviser = $this->adviser();
        for ($i = 0; $i < 7; $i++) {
            $this->documentedReport($adviser);
        }

        $this->actingAs($adviser->user)->get(route('adviser.dashboard'))->assertOk()
            ->assertViewHas('pendingEvaluations', fn ($reports) => $reports->count() === 5)
            ->assertSee('View all reviews');
    }

    public function test_resources_grid_shows_at_most_four_cards_per_row(): void
    {
        $adviser = $this->adviser();
        $html = $this->actingAs($adviser->user)->get(route('adviser.resources'))->assertOk()->getContent();

        $this->assertStringContainsString('.adviser-resource-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));', $html);
        $this->assertStringNotContainsString('minmax(260px, 1fr)', $html);
    }

    public function test_adviser_kpi_cards_carry_an_icon(): void
    {
        $adviser = $this->adviser();
        $this->actingAs($adviser->user);
        $pages = [
            route('adviser.reports'), route('adviser.reports', ['tab' => 'safety']), route('adviser.resources'),
            route('adviser.helpers'), route('adviser.referrals'), route('adviser.emergencies'), route('adviser.calendar'),
        ];
        foreach ($pages as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $cards = substr_count($html, 'class="ui-kpi ');
            $this->assertGreaterThan(0, $cards, $url);
            $this->assertSame($cards, substr_count($html, 'class="ui-kpi-icon"'), $url.' has a KPI card without an icon');
        }
    }

    public function test_reports_exports_sit_in_one_export_dropdown(): void
    {
        $adviser = $this->adviser();
        $html = $this->actingAs($adviser->user)->get(route('adviser.reports'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<details class="ui-dropdown"'));
        $this->assertMatchesRegularExpression('/<summary class="btn-secondary ui-dropdown-toggle"[^>]*>.*Export/s', $html);
        $menu = substr($html, strpos($html, '<details class="ui-dropdown"'));
        $menu = substr($menu, 0, strpos($menu, '</details>'));
        $this->assertStringContainsString('format=csv', $menu);
        $this->assertStringContainsString('format=pdf', $menu);
        $this->assertStringContainsString('Export case CSV', $menu);
        $this->assertStringContainsString('Export case PDF', $menu);
    }

    public function test_common_actions_are_icon_buttons_with_tooltips(): void
    {
        $adviser = $this->adviser();
        \App\Models\ConcernCategory::create(['concern_name' => 'Sleep', 'is_active' => true]);
        $live = \App\Models\SelfHelpResource::create(['title' => 'Read calmly', 'description' => 'A guide', 'category' => 'article', 'content' => 'Read slowly.', 'is_published' => true]);
        \App\Models\SelfHelpResource::create(['title' => 'Old guide', 'description' => 'Archived', 'category' => 'article', 'content' => 'Old.', 'is_published' => false])->forceFill(['archived_at' => now()])->save();
        $this->actingAs($adviser->user);

        $concerns = $this->get(route('concerns.manage'))->assertOk()->getContent();
        $this->assertStringContainsString('<summary class="ui-icon-btn" aria-label="Edit category" data-tooltip="Edit category">', $concerns);

        $resources = $this->get(route('adviser.resources'))->assertOk()->getContent();
        $this->assertStringContainsString('class="ui-icon-btn edit-resource"', $resources);
        $this->assertStringContainsString('aria-label="Edit resource"', $resources);
        $this->assertStringContainsString('aria-label="Archive resource"', $resources);
        $this->assertStringContainsString('#archive', $resources);
        $this->assertStringNotContainsString('<x-ui-icon name="trash"', $resources);
        $this->assertSame(1, substr_count($resources, 'aria-label="Archive resource"'), 'An archived resource has no Archive button.');
        $this->assertStringContainsString(route('adviser.resources.destroy', ['id' => $live->id]), $resources);

        $report = $this->documentedReport($adviser, 'evaluated');
        $reports = $this->get(route('adviser.reports'))->assertOk()->getContent();
        $this->assertStringContainsString('aria-label="View documentation"', $reports);
        $this->assertStringContainsString(route('adviser.session.show', $report->session_id), $reports);
    }

    public function test_concern_without_description_shows_a_muted_dash(): void
    {
        $adviser = $this->adviser();
        \App\Models\ConcernCategory::create(['concern_name' => 'Sleep', 'is_active' => true]);
        \App\Models\ConcernCategory::create(['concern_name' => 'Exams', 'description' => 'Test anxiety', 'is_active' => true]);

        $html = $this->actingAs($adviser->user)->get(route('concerns.manage'))->assertOk()->getContent();

        $this->assertStringNotContainsString('>No description<', $html);
        $this->assertStringContainsString('class="ui-muted-dash" aria-label="No description">—</span>', $html);
        $this->assertStringContainsString('Test anxiety', $html);
    }

    public function test_training_still_renders_for_helper(): void
    {
        $adviser = $this->adviser();
        $helper = $this->helperFor($adviser);

        $html = $this->actingAs($helper->user)->get(route('helper.training'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'data-page-header'));
        $this->assertSame(1, substr_count($html, '<h1'));

        $html = $this->actingAs($adviser->user)->get(route('adviser.training'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'data-page-header'));
    }

    public function test_transcript_badge_is_placed_in_the_summary_grid_and_card_grids_fit_320px(): void
    {
        $refine = file_get_contents(public_path('css/adviser-refinement.css'));
        $this->assertStringContainsString('.at-record summary>.ui-badge{justify-self:start}', $refine);
        $this->assertMatchesRegularExpression('/@media\(max-width:768px\)\{[^\n]*\.at-record summary>\.ui-badge\{grid-column:2;grid-row:1;justify-self:end\}/', $refine);

        $components = file_get_contents(public_path('css/compass-components.css'));
        $this->assertStringContainsString('minmax(min(260px, 100%), 1fr)', $components);
        $this->assertStringContainsString('minmax(min(300px, 100%), 1fr)', $components);
        $this->assertStringNotContainsString('minmax(300px, 1fr)', $components);
    }
}
