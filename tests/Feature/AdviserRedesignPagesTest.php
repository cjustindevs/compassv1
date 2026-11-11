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
}
