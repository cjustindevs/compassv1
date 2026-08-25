<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_shared_login(): void
    {
        $this->get(route('admin.audit-logs'))
            ->assertRedirect(route('login'));
    }

    public function test_non_administrator_cannot_access_audit_logs(): void
    {
        $helper = User::factory()->create(['role' => 'helper']);

        $this->actingAs($helper)
            ->get(route('admin.audit-logs'))
            ->assertForbidden();
    }

    public function test_empty_audit_table_shows_the_true_empty_state_without_preview_records(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($administrator)
            ->get(route('admin.audit-logs'));

        $response
            ->assertOk()
            ->assertSee('Audit logs')
            ->assertSee('Every privileged action, immutable and searchable.')
            ->assertSee('Recent events')
            ->assertSee('No audit events found')
            ->assertSee('System activity will appear here as actions are recorded.')
            ->assertDontSee('Preview data')
            ->assertDontSee('admin@compass')
            ->assertSee('Search audit logs...')
            ->assertSee('aria-current="page"', false)
            ->assertSee(route('admin.audit-logs'))
            ->assertDontSee('<th scope="col">Actions</th>', false)
            ->assertDontSee('data-audit-delete', false);
    }

    public function test_persisted_audit_records_are_humanized(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-14 11:00:00'));

        $administrator = User::factory()->create(['role' => 'admin']);
        $moderator = User::factory()->create([
            'name' => 'Ivan Reyes',
            'role' => 'moderator',
        ]);
        $this->audit([
            'user_account_id' => $moderator->id,
            'action' => 'PASSWORD_RESET',
            'module' => 'authentication',
            'description' => 'helper: Rina A.',
            'created_at' => CarbonImmutable::parse('2026-08-14 10:42:00'),
        ]);

        $this->actingAs($administrator)
            ->get(route('admin.audit-logs'))
            ->assertOk()
            ->assertDontSee('Preview data')
            ->assertSee('moderator: Ivan Reyes')
            ->assertSee('Password reset')
            ->assertSee('helper: Rina A.')
            ->assertSee('10:42 AM')
            ->assertSee('August 14, 2026 at 10:42 AM');
    }

    public function test_real_search_actor_category_and_date_filters_work_together(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-14 12:00:00'));

        $administrator = User::factory()->create([
            'email' => 'admin@compass.test',
            'role' => 'admin',
        ]);
        $moderator = User::factory()->create(['role' => 'moderator']);

        $this->audit([
            'user_account_id' => $administrator->id,
            'action' => 'PASSWORD_RESET',
            'module' => 'auth',
            'description' => 'helper: Rina A.',
            'created_at' => CarbonImmutable::parse('2026-08-14 10:42:00'),
        ]);
        $this->audit([
            'user_account_id' => $moderator->id,
            'action' => 'MANUAL_ASSIGNMENT',
            'module' => 'referrals',
            'description' => 'R-2413 → Maya C.',
            'created_at' => CarbonImmutable::parse('2026-08-14 10:31:00'),
        ]);
        $this->audit([
            'user_account_id' => $administrator->id,
            'action' => 'PASSWORD_RESET',
            'module' => 'auth',
            'description' => 'helper: Old Account',
            'created_at' => CarbonImmutable::parse('2026-07-01 09:00:00'),
        ]);

        $response = $this->actingAs($administrator)->get(route('admin.audit-logs', [
            'q' => 'Rina',
            'actor' => 'admin',
            'category' => 'authentication',
            'date' => 'today',
        ]));

        $response
            ->assertOk()
            ->assertSee('helper: Rina A.')
            ->assertDontSee('R-2413')
            ->assertDontSee('Old Account')
            ->assertSee('value="Rina"', false)
            ->assertSee('selected>Administrator', false)
            ->assertSee('selected>Authentication', false)
            ->assertSee('selected>Today', false);
    }

    public function test_no_matching_filters_render_the_filtered_empty_state(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $this->actingAs($administrator)
            ->get(route('admin.audit-logs', ['q' => 'not-a-real-audit-event']))
            ->assertOk()
            ->assertSee('No matching audit events')
            ->assertSee('Try changing your search or filters.')
            ->assertSee('Clear filters');
    }

    public function test_real_audit_records_are_paginated_without_mutation_controls(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        foreach (range(1, 30) as $index) {
            $this->audit([
                'user_account_id' => $administrator->id,
                'action' => 'SYSTEM_EVENT_'.$index,
                'module' => 'system',
                'description' => 'Target '.$index,
                'created_at' => now()->subMinutes($index),
            ]);
        }

        $this->actingAs($administrator)
            ->get(route('admin.audit-logs', ['per_page' => 25]))
            ->assertOk()
            ->assertSee('Showing')
            ->assertSee('1–25')
            ->assertSee('of 30 events')
            ->assertSee('Next')
            ->assertDontSee('<th scope="col">Actions</th>', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function audit(array $overrides = []): AuditLog
    {
        return AuditLog::forceCreate(array_merge([
            'user_account_id' => null,
            'action' => 'SYSTEM_EVENT',
            'module' => 'system',
            'description' => 'System target',
            'ip_address' => null,
            'user_agent' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
