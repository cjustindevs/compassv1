<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\ReportCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use RuntimeException;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_shared_login(): void
    {
        $this->get(route('admin.reports'))
            ->assertRedirect(route('login'));
    }

    public function test_non_administrator_cannot_access_reports(): void
    {
        $helper = User::factory()->create(['role' => 'helper']);

        $this->actingAs($helper)
            ->get(route('admin.reports'))
            ->assertForbidden();
    }

    public function test_administrator_can_view_the_data_driven_report_catalog(): void
    {
        CarbonImmutable::setTestNow('2026-08-14 10:30:00');
        $administrator = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($administrator)
            ->get(route('admin.reports', ['q' => 'activity']));

        $response
            ->assertOk()
            ->assertSee('COMPASS')
            ->assertSee('Reports')
            ->assertSee('Generate exports for the counseling office, university admin, or research team.')
            ->assertSee('Monthly session report')
            ->assertSee('Referral outcomes')
            ->assertSee('Helper competency growth')
            ->assertSee('Performance benchmarks')
            ->assertSee('Users &amp; activity', false)
            ->assertSee('Emergency incident log')
            ->assertSee('Updated Aug 14')
            ->assertSee('data-report-records="1"', false)
            ->assertSee('value="activity"', false)
            ->assertSee('Filter reports')
            ->assertSee('New report')
            ->assertSee('No secure formats configured')
            ->assertSee('View Monthly session report catalog preview')
            ->assertSee('Download Monthly session report (unavailable)')
            ->assertSee('aria-current="page"', false)
            ->assertSee(route('admin.reports'))
            ->assertDontSee('href="/storage/', false)
            ->assertDontSee('href="/reports/download', false);
    }

    public function test_report_catalog_uses_real_source_activity_instead_of_mock_updated_dates(): void
    {
        CarbonImmutable::setTestNow('2026-08-10 09:00:00');
        $administrator = User::factory()->create(['role' => 'admin']);

        CarbonImmutable::setTestNow('2026-08-13 15:45:00');
        User::factory()->create(['role' => 'helper']);

        $this->actingAs($administrator)
            ->get(route('admin.reports'))
            ->assertOk()
            ->assertSee('data-report-id="users-activity"', false)
            ->assertSee('data-report-records="2"', false)
            ->assertSee('Updated Aug 13');
    }

    public function test_empty_report_catalog_has_a_contained_empty_state(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $this->app->instance(ReportCatalog::class, new class extends ReportCatalog
        {
            public function reports(): Collection
            {
                return collect();
            }
        });

        $this->actingAs($administrator)
            ->get(route('admin.reports'))
            ->assertOk()
            ->assertSee('No reports configured')
            ->assertSee('Report definitions will appear here once they are available.');
    }

    public function test_catalog_failure_renders_a_contained_error_without_sensitive_details(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $this->app->instance(ReportCatalog::class, new class extends ReportCatalog
        {
            public function reports(): Collection
            {
                throw new RuntimeException('private-report-storage-token');
            }
        });

        $this->actingAs($administrator)
            ->get(route('admin.reports'))
            ->assertOk()
            ->assertSee('Unable to load reports')
            ->assertSee('Please try again.')
            ->assertDontSee('private-report-storage-token');
    }

    public function test_report_mutation_and_download_routes_are_not_exposed_without_a_backend(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $this->actingAs($administrator)
            ->post('/admin/reports')
            ->assertMethodNotAllowed();

        $this->actingAs($administrator)
            ->get('/admin/reports/monthly-session/download')
            ->assertNotFound();
    }
}
