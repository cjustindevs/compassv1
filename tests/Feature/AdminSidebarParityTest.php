<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin rail is supposed to read as the same sidebar as every other role.
 * These tests pin the shared class contract and the shared metrics so the admin
 * shell cannot quietly drift back into its own visual language.
 */
class AdminSidebarParityTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        return User::factory()->create([
            'name' => 'Amelia Santos',
            'role' => 'admin',
            'dark_mode' => false,
        ]);
    }

    private function desktopAdminPage(string $route)
    {
        return $this->actingAs($this->administrator())
            ->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh) AppleWebKit Safari/605.1.15')
            ->get(route($route));
    }

    /**
     * Just the <aside> the rail renders, so assertions cannot be satisfied (or
     * broken) by unrelated admin page markup that happens to share a class name.
     */
    private function adminSidebarMarkup(): string
    {
        $html = $this->desktopAdminPage('admin.settings')->assertOk()->getContent();

        $this->assertSame(
            1,
            preg_match('/<aside class="admin-sidebar".*?<\/aside>/s', $html, $matches),
            'Expected exactly one <aside class="admin-sidebar"> rail.'
        );

        return $matches[0];
    }

    public function test_admin_sidebar_uses_the_shared_sidebar_class_contract(): void
    {
        $response = $this->desktopAdminPage('admin.settings')->assertOk();

        // Header, brand lockup and collapse toggle.
        $response->assertSee('class="sidebar-header"', false);
        $response->assertSee('class="sidebar-brand"', false);
        $response->assertSee('class="sidebar-toggle"', false);
        $response->assertSee('brand-logo-icon', false);
        $response->assertSee('brand-wordmark', false);

        // Navigation.
        $response->assertSee('class="sidebar-nav"', false);
        $response->assertSee('class="nav-section"', false);
        $response->assertSee('class="nav-item', false);
        $response->assertSee('class="nav-text"', false);

        // Footer: user card, profile link, logout.
        $response->assertSee('class="sidebar-footer"', false);
        $response->assertSee('class="sidebar-user"', false);
        $response->assertSee('class="user-avatar"', false);
        $response->assertSee('class="user-info"', false);
        $response->assertSee('class="user-name"', false);
        $response->assertSee('class="user-role"', false);
        $response->assertSee('class="view-profile"', false);
        $response->assertSee('class="sidebar-logout-button"', false);
        $response->assertSee('class="logout-text"', false);
    }

    public function test_admin_sidebar_no_longer_uses_its_own_legacy_class_names(): void
    {
        $html = $this->adminSidebarMarkup();

        foreach ([
            'sidebar-brand-row',
            'sidebar-brand-mark',
            'sidebar-brand-copy',
            'sidebar-navigation',
            'sidebar-section',
            'sidebar-link',
            'sidebar-account',
            'sidebar-profile-link',
            'avatar-green',
        ] as $legacyClass) {
            $this->assertStringNotContainsString(
                $legacyClass,
                $html,
                "The admin sidebar should no longer render the legacy [{$legacyClass}] class."
            );
        }
    }

    public function test_every_admin_navigation_entry_is_reachable_from_the_rail(): void
    {
        $html = $this->desktopAdminPage('admin.settings')
            ->assertOk()
            ->getContent();

        foreach ([
            'admin.dashboard',
            'admin.users',
            'admin.roles-permissions',
            'admin.resource-library',
            'admin.audit-logs',
            'admin.backup-restore',
            'admin.system-health',
            'admin.reports',
            'admin.settings',
        ] as $routeName) {
            $this->assertStringContainsString(
                route($routeName),
                $html,
                "The admin rail is missing a link to [{$routeName}]."
            );
        }
    }

    public function test_only_the_current_admin_page_is_marked_active(): void
    {
        $html = $this->adminSidebarMarkup();

        $this->assertMatchesRegularExpression(
            '/<a\s+class="nav-item active"\s+href="'.preg_quote(route('admin.settings'), '/').'"\s+aria-current="page"\s*>/',
            $html
        );

        // Exactly one active entry, so the accent bar never appears twice.
        $this->assertSame(1, substr_count($html, 'class="nav-item active"'));
    }

    public function test_admin_rail_matches_the_shared_sidebar_metrics(): void
    {
        // 264px rail, same as resources/css/sidebar.css.
        $this->assertMatchesRegularExpression(
            '/--sidebar-width:\s*264px/',
            file_get_contents(public_path('css/admin-dashboard.css')),
            'The admin rail must use the shared 264px sidebar width.'
        );

        $this->assertMatchesRegularExpression(
            '/--sidebar-w:\s*264px/',
            file_get_contents(resource_path('css/sidebar.css')),
            'The shared sidebar width must stay 264px for the rails to match.'
        );

        // Both rails collapse to the same 76px icon rail.
        $this->assertStringContainsString(
            'width: 76px',
            file_get_contents(resource_path('views/components/admin/sidebar.blade.php'))
        );
        $this->assertStringContainsString(
            '--sidebar-w-collapsed: 76px',
            file_get_contents(resource_path('css/sidebar.css'))
        );
    }

    public function test_admin_rail_reuses_the_shared_collapse_storage_key(): void
    {
        $response = $this->desktopAdminPage('admin.settings')->assertOk();

        // Same key as layouts/partials/sidebar-critical.blade.php, so one
        // preference survives a switch between the admin area and a role area.
        $response->assertSee("STORAGE_KEY = 'sidebarCollapsed'", false);
        $response->assertSee('id="adminSidebarToggle"', false);
    }
}
