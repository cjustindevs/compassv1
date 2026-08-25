<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\BackupCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use RuntimeException;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_shared_login(): void
    {
        $this->get(route('admin.backup-restore'))
            ->assertRedirect(route('login'));
    }

    public function test_non_administrator_cannot_access_backup_and_restore(): void
    {
        $helper = User::factory()->create(['role' => 'helper']);

        $this->actingAs($helper)
            ->get(route('admin.backup-restore'))
            ->assertForbidden();
    }

    public function test_administrator_sees_the_truthful_unconfigured_empty_state_and_safe_dialogs(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($administrator)
            ->get(route('admin.backup-restore'));

        $response
            ->assertOk()
            ->assertSee('Backup &amp; restore', false)
            ->assertSee('Automated nightly snapshots with 30-day retention.')
            ->assertSee('Recent snapshots')
            ->assertSee('Backend not configured')
            ->assertSee('No backups available')
            ->assertSee('Backup generation is not configured in this environment.')
            ->assertSee('Run backup now')
            ->assertSee('Restore backup?')
            ->assertSee('Type RESTORE to continue.')
            ->assertSee('data-confirm-system-restore disabled', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee(route('admin.backup-restore'))
            ->assertDontSee('2026-11-05 03:00')
            ->assertDontSee('data-backup-snapshot', false);
    }

    public function test_snapshot_catalog_is_data_driven_sorted_and_safely_disables_unavailable_operations(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $this->app->instance(BackupCatalog::class, $this->catalog([
            [
                'id' => 'older-backup',
                'createdAt' => '2026-11-03T03:00:00+08:00',
                'sizeBytes' => 4402341478,
                'status' => 'failed',
                'downloadUrl' => '/private/older-backup',
            ],
            [
                'id' => 'newest-backup',
                'createdAt' => '2026-11-05T03:00:00+08:00',
                'sizeBytes' => 4509715661,
                'status' => 'completed',
                'downloadUrl' => '/private/newest-backup',
            ],
        ]));

        $this->actingAs($administrator)
            ->get(route('admin.backup-restore'))
            ->assertOk()
            ->assertSeeInOrder(['2026-11-05 03:00', '2026-11-03 03:00'])
            ->assertSee('4.2 GB')
            ->assertSee('4.1 GB')
            ->assertSee('Backup failed')
            ->assertSee('data-backup-snapshot', false)
            ->assertSee('A protected backup download service is not configured')
            ->assertSee('A protected restore service is not configured')
            ->assertDontSee('/private/newest-backup')
            ->assertDontSee('/private/older-backup');
    }

    public function test_backup_catalog_failure_renders_a_contained_error_state(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $this->app->instance(BackupCatalog::class, new class extends BackupCatalog
        {
            public function snapshots(): Collection
            {
                throw new RuntimeException('Sensitive internal backup failure');
            }
        });

        $this->actingAs($administrator)
            ->get(route('admin.backup-restore'))
            ->assertOk()
            ->assertSee('Unable to load backup history')
            ->assertSee('Please try again.')
            ->assertDontSee('Sensitive internal backup failure');
    }

    public function test_no_backup_mutation_endpoint_is_exposed_without_a_real_provider(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $this->actingAs($administrator)
            ->post(route('admin.backup-restore'))
            ->assertMethodNotAllowed();
    }

    /**
     * @param  array<int, array<string, mixed>>  $snapshots
     */
    private function catalog(array $snapshots): BackupCatalog
    {
        return new class($snapshots) extends BackupCatalog
        {
            /**
             * @param  array<int, array<string, mixed>>  $snapshots
             */
            public function __construct(private readonly array $snapshots) {}

            public function snapshots(): Collection
            {
                return collect($this->snapshots);
            }
        };
    }
}
