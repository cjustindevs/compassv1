<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\SystemHealthMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_shared_login(): void
    {
        $this->get(route('admin.system-health'))
            ->assertRedirect(route('login'));
    }

    public function test_non_administrator_cannot_access_system_health(): void
    {
        $helper = User::factory()->create(['role' => 'helper']);

        $this->actingAs($helper)
            ->get(route('admin.system-health'))
            ->assertForbidden();
    }

    public function test_administrator_can_view_the_data_driven_preview_health_dashboard(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($administrator)
            ->get(route('admin.system-health'));

        $response
            ->assertOk()
            ->assertSee('System Health')
            ->assertSee('Real-time infrastructure and service monitoring.')
            ->assertSee('Preview metrics are shown')
            ->assertSee('Overall status: Degraded')
            ->assertSee('Incidents (2)')
            ->assertSee('Server Status')
            ->assertSee('Voice Call Service')
            ->assertSee('Healthy')
            ->assertSee('Warning')
            ->assertSee('Critical')
            ->assertSee('CPU Usage (24h)')
            ->assertSee('Memory Usage (24h)')
            ->assertSee('API Response Time')
            ->assertSee('stroke-dasharray="10 8"', false)
            ->assertSee('Storage Usage')
            ->assertSee('184 GB')
            ->assertSee('System Uptime')
            ->assertSee('99.98%')
            ->assertSee('Recent errors &amp; warnings', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee(route('admin.system-health'))
            ->assertDontSee('Restart server')
            ->assertDontSee('Restart database');
    }

    public function test_overall_status_is_calculated_from_provider_service_states(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);
        $snapshot = (new SystemHealthMonitor)->snapshot();
        $snapshot['services'][0]['status'] = 'critical';

        $this->app->instance(SystemHealthMonitor::class, new class($snapshot) extends SystemHealthMonitor
        {
            /**
             * @param  array<string, mixed>  $snapshot
             */
            public function __construct(private readonly array $snapshot) {}

            public function snapshot(): array
            {
                return $this->snapshot;
            }
        });

        $this->actingAs($administrator)
            ->get(route('admin.system-health'))
            ->assertOk()
            ->assertSee('Overall status: Critical')
            ->assertSee('health-service-critical', false);
    }

    public function test_monitoring_failure_renders_a_contained_error_without_sensitive_details(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $this->app->instance(SystemHealthMonitor::class, new class extends SystemHealthMonitor
        {
            public function snapshot(): array
            {
                throw new RuntimeException('monitor-token-and-private-host');
            }
        });

        $this->actingAs($administrator)
            ->get(route('admin.system-health'))
            ->assertOk()
            ->assertSee('Unable to retrieve system health')
            ->assertSee('Some monitoring information may be unavailable. Please try again.')
            ->assertDontSee('monitor-token-and-private-host');
    }

    public function test_health_page_is_a_simple_status_list_with_details_collapsed(): void
    {
        // QA Nov 24 (Admin): make the system health interface more simple.
        $html = $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.system-health'))->assertOk()->getContent();
        $this->assertStringContainsString('<section class="health-service-list"', $html);
        $this->assertStringContainsString('class="health-service-row health-service-', $html);
        $this->assertStringContainsString('<details class="health-details">', $html, 'Charts, storage and uptime are one click away.');
        $this->assertLessThan(strpos($html, '<details class="health-details">'), strpos($html, 'id="health-incidents"'), 'Recent errors stay visible above the details.');
        $this->assertStringNotContainsString('Voice recordings', $html, 'Calls are never recorded, so preview data must not suggest recordings.');
    }
}
