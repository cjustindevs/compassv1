<?php

namespace Tests\Feature;

use App\Support\FilterChips;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class UiComponentsTest extends TestCase
{
    public function test_page_header_renders_title_and_actions_and_leaves_the_bell_in_the_top_bar(): void
    {
        $html = Blade::render('<x-ui.page-header title="Reports" description="All reports"><x-slot:actions><a href="/x" id="act">Export</a></x-slot:actions></x-ui.page-header>');

        $this->assertStringContainsString('data-page-header', $html);
        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Reports', $html);
        $this->assertStringContainsString('<a href="/x" id="act">Export</a>', $html);
        $this->assertStringNotContainsString('data-notification-slot', $html);
    }

    public function test_kpi_puts_label_above_value_and_applies_tone(): void
    {
        $html = Blade::render('<x-ui.kpi-grid><x-ui.kpi label="Open cases" value="12" tone="red" /></x-ui.kpi-grid>');

        $this->assertLessThan(strpos($html, 'ui-kpi-value'), strpos($html, 'ui-kpi-label'));
        $this->assertStringContainsString('ui-kpi--red', $html);
        $this->assertStringContainsString('ui-kpis', $html);
    }

    public function test_kpi_icon_is_optional(): void
    {
        $plain = Blade::render('<x-ui.kpi label="Open" value="3" />');
        $this->assertStringNotContainsString('ui-kpi-icon', $plain);

        $html = Blade::render('<x-ui.kpi label="Open" value="3" icon="clipboard" />');
        $this->assertStringContainsString('ui-kpi-icon', $html);
        $this->assertLessThan(strpos($html, 'ui-kpi-value'), strpos($html, 'ui-kpi-label'));
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

    public function test_table_stack_mode_marks_wrapper_for_phone_cards(): void
    {
        $html = Blade::render('<x-ui.table :stack="true"><thead><tr><th>Name</th></tr></thead><tbody><tr><td data-label="Name">Ana</td></tr></tbody></x-ui.table>');

        $this->assertStringContainsString('ui-table--stack', $html);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('data-label="Name"', $html);

        $plain = Blade::render('<x-ui.table><tbody><tr><td>x</td></tr></tbody></x-ui.table>');
        $this->assertStringNotContainsString('ui-table--stack', $plain);
    }

    public function test_list_row_renders_avatar_title_and_actions(): void
    {
        $html = Blade::render('<x-ui.list-row initials="AB" title="Ana Bell" subtitle="Grade 7"><x-slot:badge>Active</x-slot:badge><x-slot:actions><a href="/v">View</a></x-slot:actions></x-ui.list-row>');

        $this->assertStringContainsString('ui-list-row', $html);
        $this->assertStringContainsString('AB', $html);
        $this->assertStringContainsString('Ana Bell', $html);
        $this->assertStringContainsString('Grade 7', $html);
        $this->assertStringContainsString('<a href="/v">View</a>', $html);
        $this->assertStringContainsString('Active', $html);
    }

    public function test_pagination_view_shows_range_and_pills(): void
    {
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(range(1, 10), 19, 10, 1, ['path' => '/helpers']);
        $html = $paginator->links('vendor.pagination.compass', ['noun' => 'helpers'])->toHtml();

        $this->assertStringContainsString('Showing 1–10 of 19 helpers', $html);
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>\s*1\s*</', $html);
        $this->assertStringContainsString('page=2', $html);
        $this->assertStringContainsString('ui-pagination', $html);
    }

    public function test_pagination_last_page_disables_next(): void
    {
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(range(11, 19), 19, 10, 2, ['path' => '/helpers']);
        $html = $paginator->links('vendor.pagination.compass', ['noun' => 'helpers'])->toHtml();

        $this->assertStringContainsString('Showing 11–19 of 19 helpers', $html);
        $this->assertMatchesRegularExpression('/aria-disabled="true"[^>]*aria-label="Next page"/', $html);
        $this->assertStringNotContainsString('rel="next"', $html);
        $this->assertStringContainsString('rel="prev"', $html);
    }

    public function test_actions_cell_child_rules_exclude_dialogs(): void
    {
        $css = file_get_contents(public_path('css/compass-components.css'));

        preg_match_all('/td\.ui-actions\s*>\s*([^{\s]+)\s*\{[^}]*margin-left/', $css, $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $selector) {
            $this->assertStringContainsString(':not(dialog)', $selector);
        }
    }

    public function test_empty_state_honours_the_hidden_attribute(): void
    {
        $css = file_get_contents(public_path('css/compass-components.css'));

        $this->assertMatchesRegularExpression('/\.ui-empty\[hidden\]\s*\{\s*display:\s*none/', $css);
    }

    public function test_shared_table_rules_never_force_a_min_width_without_the_opt_in_modifier(): void
    {
        $css = file_get_contents(public_path('css/compass-components.css'));

        $this->assertDoesNotMatchRegularExpression('/\.ui-table\s+table\s*\{[^}]*min-width/', $css);
        $this->assertDoesNotMatchRegularExpression('/\.ui-table(?!\.ui-table--wide)[^{,]*table\s*\{[^}]*min-width/', $css);
    }
}
