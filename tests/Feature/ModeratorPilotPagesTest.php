<?php

namespace Tests\Feature;

use App\Models\HelpSeeker;
use App\Models\QueueRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModeratorPilotPagesTest extends TestCase
{
    use RefreshDatabase;

    private function moderator(): User
    {
        return User::factory()->create(['role' => 'moderator', 'is_active' => true]);
    }

    public function test_dashboard_uses_shared_shell_and_header(): void
    {
        $response = $this->actingAs($this->moderator())->get(route('moderator.dashboard'))->assertOk()
            ->assertSee('data-page-header', false)->assertSee('co-kpis', false);
        $this->assertSame(1, substr_count($response->getContent(), 'data-page-slot'));
    }

    public function test_attention_strip_lists_only_waiting_work(): void
    {
        $this->actingAs($this->moderator());
        $this->get(route('moderator.dashboard'))->assertOk()->assertDontSee('Needs your attention')->assertDontSee('ui-attention', false);

        $user = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        $seeker = HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'Alias'.$user->id]);
        QueueRequest::create(['seeker_id' => $seeker->id, 'request_status' => 'waiting', 'priority_level' => 'low', 'request_date' => now()]);

        $this->get(route('moderator.dashboard'))->assertOk()->assertSee('Needs your attention')
            ->assertSee('Waiting in queue')->assertSee(route('moderator.queue'), false)->assertDontSee('Emergencies open')->assertDontSee('Emergency open');
    }

    public function test_attention_queue_count_matches_the_kpi_and_ignores_test_records(): void
    {
        $this->actingAs($this->moderator());
        $user = User::factory()->create(['role' => 'seeker', 'is_active' => true]);
        $seeker = HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'Alias'.$user->id]);
        QueueRequest::create(['seeker_id' => $seeker->id, 'request_status' => 'waiting', 'priority_level' => 'low', 'request_date' => now()]);
        QueueRequest::create(['seeker_id' => $seeker->id, 'request_status' => 'waiting', 'priority_level' => 'low', 'request_date' => now(), 'is_test_record' => true]);

        $html = $this->get(route('moderator.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/ui-attention-count">\s*1\s*<\/strong>\s*<span class="ui-attention-label">Waiting in queue/', $html);
        $this->assertMatchesRegularExpression('/Waiting requests<\/h3>\s*<strong\s+id="statQueue"\s*>\s*1\s*</', $html);
    }

    public function test_each_moderator_kpi_appears_once_and_stays_live(): void
    {
        $html = $this->actingAs($this->moderator())->get(route('moderator.dashboard'))->assertOk()->getContent();
        foreach (['Waiting requests', 'Available Helpers', 'Active sessions', 'Active emergencies'] as $label) {
            $this->assertSame(1, preg_match_all('/<h3[^>]*>'.preg_quote($label, '/').'<\/h3>/', $html), $label);
        }
        $this->assertStringNotContainsString('ui-kpi', $html);
        foreach (['statQueue', 'statSessions', 'statHelpers', 'statEmergency'] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $html);
        }
    }
}
