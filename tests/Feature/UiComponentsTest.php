<?php

namespace Tests\Feature;

use App\Support\FilterChips;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class UiComponentsTest extends TestCase
{
    public function test_page_header_renders_title_actions_and_single_bell_slot(): void
    {
        $html = Blade::render('<x-ui.page-header title="Reports" description="All reports"><x-slot:actions><a href="/x" id="act">Export</a></x-slot:actions></x-ui.page-header>');

        $this->assertStringContainsString('data-page-header', $html);
        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Reports', $html);
        $this->assertStringContainsString('<a href="/x" id="act">Export</a>', $html);
        $this->assertSame(1, substr_count($html, 'data-page-slot'));
    }

    public function test_kpi_puts_label_above_value_and_applies_tone(): void
    {
        $html = Blade::render('<x-ui.kpi-grid><x-ui.kpi label="Open cases" value="12" tone="red" /></x-ui.kpi-grid>');

        $this->assertLessThan(strpos($html, 'ui-kpi-value'), strpos($html, 'ui-kpi-label'));
        $this->assertStringContainsString('ui-kpi--red', $html);
        $this->assertStringContainsString('ui-kpis', $html);
    }

    public function test_filter_chips_remove_one_filter_and_keep_the_rest(): void
    {
        $request = Request::create('/reports', 'GET', ['case_status' => 'waiting', 'priority' => 'high', 'tab' => 'safety']);
        app()->instance('request', $request);

        $chips = FilterChips::from($request, ['case_status' => 'Status', 'priority' => 'Priority']);

        $this->assertCount(2, $chips);
        $priority = collect($chips)->firstWhere('label', 'Priority: high');
        $this->assertNotNull($priority);
        $this->assertStringContainsString('case_status=waiting', $priority['href']);
        $this->assertStringContainsString('tab=safety', $priority['href']);
        $this->assertStringNotContainsString('priority=', $priority['href']);

        $html = Blade::render('<x-ui.filter-chips :chips="$chips" reset-href="/reports" />', ['chips' => $chips]);
        $this->assertStringContainsString('Priority: high', $html);
        $this->assertSame(2, substr_count($html, 'class="ui-chip"'));
        $this->assertStringContainsString('Clear all', $html);
    }

    public function test_empty_state_shows_one_action(): void
    {
        $html = Blade::render('<x-ui.empty title="Nothing here" text="Come back later"><a href="/go">Go</a></x-ui.empty>');

        $this->assertStringContainsString('ui-empty', $html);
        $this->assertStringContainsString('Nothing here', $html);
        $this->assertSame(1, substr_count($html, '<a '));
    }
}
