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

    public function test_status_badge_gives_each_session_status_its_own_colour(): void
    {
        $badge = fn (string $status) => Blade::render('<x-ui.status-badge :status="$s" />', ['s' => $status]);

        $this->assertStringContainsString('ui-badge--green">Completed<', $badge('completed'));
        $this->assertStringContainsString('ui-badge--blue">Evaluated<', $badge('evaluated'));
        $this->assertStringContainsString('ui-badge--red">Cancelled<', $badge('cancelled'));
        $this->assertStringContainsString('ui-badge--orange">No Show<', $badge('no_show'));
        $this->assertStringContainsString('ui-badge--amber">Active<', $badge('active'));
        $this->assertStringContainsString('ui-badge--neutral">Something Else<', $badge('something_else'));
    }

    public function test_tabs_mark_the_current_tab_and_show_counts(): void
    {
        $html = Blade::render('<x-ui.tabs label="Views" :items="$items" />', ['items' => [
            ['label' => 'Active', 'href' => '/a', 'active' => true, 'count' => 3],
            ['label' => 'Resolved', 'href' => '/r', 'active' => false],
        ]]);

        $this->assertStringContainsString('<nav class="ui-tabs" aria-label="Views">', $html);
        $this->assertMatchesRegularExpression('#<a href="/a" class="ui-tab is-active"\s+aria-current="page"\s*>Active <span>3</span></a>#', $html);
        $this->assertMatchesRegularExpression('#<a href="/r" class="ui-tab"\s*>Resolved</a>#', $html);
    }

    public function test_the_same_tab_bar_is_used_on_reports_emergencies_and_schedules(): void
    {
        foreach (['adviser/reports', 'adviser/emergencies', 'moderator/schedules', 'moderator/reports'] as $view) {
            $source = file_get_contents(resource_path("views/{$view}.blade.php"));
            $this->assertStringContainsString('<x-ui.tabs', $source, $view);
        }
        $this->assertStringNotContainsString('class="duty-tabs"', file_get_contents(resource_path('views/moderator/schedules.blade.php')));
    }

    public function test_icon_button_has_a_name_a_tooltip_and_keeps_extra_attributes(): void
    {
        $link = Blade::render('<x-ui.icon-button icon="eye" label="View documentation" href="/x" />');
        $this->assertStringContainsString('<a href="/x"', $link);
        $this->assertStringContainsString('class="ui-icon-btn"', $link);
        $this->assertStringContainsString('aria-label="View documentation"', $link);
        $this->assertStringContainsString('data-tooltip="View documentation"', $link);
        $this->assertStringContainsString('#eye', $link);

        $submit = Blade::render('<x-ui.icon-button icon="archive" label="Archive" type="submit" data-confirm="Archive this?" />');
        $this->assertStringContainsString('<button type="submit"', $submit);
        $this->assertStringContainsString('data-confirm="Archive this?"', $submit);
        $this->assertStringContainsString('#archive', $submit);
    }

    public function test_primary_buttons_use_the_darker_brand_green_for_readable_white_text(): void
    {
        $css = file_get_contents(public_path('css/compass-ui.css'));
        $this->assertStringContainsString(":is(.btn-primary, .ui-button-primary) {\n    color: #fff; background: var(--compass-green-dark); border: 1px solid var(--compass-green-dark);", str_replace("\r\n", "\n", $css));
        $this->assertStringContainsString(':is(.btn-primary, .ui-button-primary):hover:not(:disabled) { background: #01562b; border-color: #01562b;', $css);
    }

    public function test_sign_in_links_are_readable_and_not_marked_by_colour_alone(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('password.request').'" class="text-sm text-green-700 underline-offset-2 hover:underline"', $html);
        $this->assertStringContainsString('href="'.route('register').'" class="text-green-700 font-medium underline underline-offset-2"', $html);
        $this->assertStringNotContainsString('text-green-600 font-medium hover:underline">Get Started', $html);
    }

    public function test_every_kpi_card_has_a_subtle_border_matching_its_tone(): void
    {
        $css = file_get_contents(public_path('css/compass-components.css'));

        $this->assertStringNotContainsString('border: 1px solid transparent; text-align: center;', $css, 'KPI cards must not hide their border.');
        $this->assertMatchesRegularExpression('/\.ui-kpi \{[^}]*border: 1px solid var\(--compass-border\)/', $css);
        foreach (['green' => '#bbf7d0', 'amber' => '#fde68a', 'blue' => '#bfdbfe', 'red' => '#fecaca'] as $tone => $border) {
            $this->assertStringContainsString(".ui-kpi--{$tone} { background: ", $css);
            $this->assertMatchesRegularExpression('/\.ui-kpi--'.$tone.' \{[^}]*border-color: '.$border.'/', $css);
        }
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

    public function test_accessibility_fixes_stay_in_place(): void
    {
        $assets = file_get_contents(resource_path('views/partials/ui-assets.blade.php'));
        $this->assertGreaterThan(strpos($assets, 'css/compass-layout.css'), strpos($assets, 'css/accessibility-contrast.css'), 'Contrast fixes load last.');
        $css = file_get_contents(public_path('css/accessibility-contrast.css'));
        foreach (['.sr-only', '--green-600: #087341', '--compass-danger: #b91c1c', '.text-gray-400', 'compass-badge-ring', 'prefers-reduced-motion'] as $needle) $this->assertStringContainsString($needle, $css);
        $this->assertDoesNotMatchRegularExpression('/<span aria-disabled="true" aria-label=/', file_get_contents(resource_path('views/vendor/pagination/tailwind.blade.php')));
        foreach (['professional/profile', 'helper/profile', 'helper/settings', 'moderator/settings'] as $view) {
            $html = file_get_contents(resource_path("views/$view.blade.php"));
            $this->assertDoesNotMatchRegularExpression('/<label(?![^>]*\bfor=)[^>]*>[^<]*<\/label>\s*<(input|select|textarea)\b(?![^>]*\bid=)(?![^>]*type="hidden")[^>]*\bname=/', $html, "$view has an unlinked label");
        }
        $this->assertStringContainsString('aria-label="Accept new referrals"', file_get_contents(resource_path('views/professional/settings.blade.php')));
        $this->assertStringContainsString('<span class="dot" role="img" aria-label="Unread">', file_get_contents(resource_path('views/moderator/notifications.blade.php')));
    }

    public function test_phone_layout_fixes_stay_in_place(): void
    {
        $assets = file_get_contents(resource_path('views/partials/ui-assets.blade.php'));
        $this->assertStringContainsString("js/mobile-tables.js", $assets, 'Every page labels its table cells for phones.');
        $css = file_get_contents(public_path('css/compass-components.css'));
        $this->assertStringContainsString('table.compass-stack-table td[data-label]::before { content: attr(data-label);', $css);
        $this->assertStringContainsString('td.compass-stack-wide) { grid-column: 1 / -1; }', $css);
        $calendar = file_get_contents(resource_path('views/helper/calendar.blade.php'));
        $this->assertStringContainsString('<nav class="hf-cal-nav" aria-label="Choose a month">', $calendar);
        $this->assertStringContainsString('aria-label="Previous month"', $calendar);
        // Darkening solid green buttons must not paint the white "secondary" buttons green.
        $this->assertStringContainsString('.seeker-button:not(.secondary):not(.danger)', file_get_contents(public_path('css/accessibility-contrast.css')));
    }

    public function test_pagination_last_page_disables_next(): void
    {
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(range(11, 19), 19, 10, 2, ['path' => '/helpers']);
        $html = $paginator->links('vendor.pagination.compass', ['noun' => 'helpers'])->toHtml();

        $this->assertStringContainsString('Showing 11–19 of 19 helpers', $html);
        // The label is screen-reader text inside the disabled span (aria-label is not allowed on a plain span).
        $this->assertMatchesRegularExpression('/<span class="ui-page ui-page--disabled" aria-disabled="true">[\s\S]*?<span class="sr-only">Next page<\/span><\/span>/', $html);
        $this->assertStringNotContainsString('aria-disabled="true" aria-label', $html);
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

    public function test_a_header_bell_stays_on_the_title_row_instead_of_wrapping_below(): void
    {
        $css = file_get_contents(public_path('css/compass-layout.css'));

        // Page top bars may wrap so real action buttons never collide with the title...
        $this->assertStringContainsString('flex-wrap: wrap; column-gap: 16px; row-gap: 12px;', $css);
        // ...but when the bell is the only thing on the right, the row stays on one line.
        $this->assertMatchesRegularExpression('/:has\(> \.compass-notices\.is-header-action\)\s*\{\s*flex-wrap: nowrap;/', $css);
    }

    public function test_shared_table_rules_never_force_a_min_width_without_the_opt_in_modifier(): void
    {
        $css = file_get_contents(public_path('css/compass-components.css'));

        $this->assertDoesNotMatchRegularExpression('/\.ui-table\s+table\s*\{[^}]*min-width/', $css);
        $this->assertDoesNotMatchRegularExpression('/\.ui-table(?!\.ui-table--wide)[^{,]*table\s*\{[^}]*min-width/', $css);
    }
}
