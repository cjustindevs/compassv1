<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\ActiveSessionCatalog;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_shared_login(): void
    {
        $this->get(route('admin.settings'))
            ->assertRedirect(route('login'));
    }

    public function test_non_administrator_cannot_access_admin_settings(): void
    {
        $helper = User::factory()->create(['role' => 'helper']);

        $this->actingAs($helper)
            ->get(route('admin.settings'))
            ->assertForbidden();
    }

    public function test_administrator_can_view_settings_in_the_shared_admin_layout(): void
    {
        $administrator = User::factory()->create([
            'name' => 'Amelia Santos',
            'role' => 'admin',
            'email_notifications' => true,
            'show_email' => true,
            'allow_data_research' => false,
            'dark_mode' => true,
        ]);

        $response = $this->actingAs($administrator)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh) AppleWebKit Safari/605.1.15')
            ->get(route('admin.settings'));

        $response
            ->assertOk()
            ->assertSee('COMPASS')
            ->assertSee('View Profile &amp; Settings', false)
            ->assertSee('class="sidebar-logout-button"', false)
            ->assertSee(route('admin.settings'))
            ->assertSee(route('logout'))
            ->assertSee('Personalize your workspace and safeguard your account.')
            ->assertSee('Appearance')
            ->assertSee('Notifications')
            ->assertSee('Privacy')
            ->assertSee('Security')
            ->assertSee('Language')
            ->assertSee('data-settings-default-theme="dark"', false)
            ->assertSee('data-setting-key="email_notifications"', false)
            ->assertSee('data-setting-key="show_email"', false)
            ->assertSee('No verified administrator phone or SMS provider is configured.')
            ->assertSee('An authenticator-app 2FA backend is not installed.')
            ->assertSee('English')
            ->assertSee('Safari on macOS')
            ->assertSee('Session revocation is not implemented.')
            ->assertSee(route('password.update'))
            ->assertSee(route('admin.settings.preference.update'))
            ->assertSee('aria-current="page"', false)
            ->assertDontSee('Sign out all other sessions');
    }

    public function test_administrator_can_persist_supported_account_preferences(): void
    {
        $administrator = User::factory()->create([
            'role' => 'admin',
            'email_notifications' => true,
            'show_email' => false,
        ]);

        $this->actingAs($administrator)
            ->patchJson(route('admin.settings.preference.update'), [
                'setting' => 'email_notifications',
                'enabled' => false,
            ])
            ->assertOk()
            ->assertJson([
                'saved' => true,
                'setting' => 'email_notifications',
                'enabled' => false,
            ]);

        $this->actingAs($administrator)
            ->patchJson(route('admin.settings.preference.update'), [
                'setting' => 'show_email',
                'enabled' => true,
            ])
            ->assertOk();

        $administrator->refresh();
        $this->assertFalse($administrator->email_notifications);
        $this->assertTrue($administrator->show_email);
    }

    public function test_unsupported_preference_is_rejected_without_mutating_user(): void
    {
        $administrator = User::factory()->create([
            'role' => 'admin',
            'email_notifications' => true,
        ]);

        $this->actingAs($administrator)
            ->patchJson(route('admin.settings.preference.update'), [
                'setting' => 'two_factor_authentication',
                'enabled' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('setting');

        $this->assertTrue($administrator->refresh()->email_notifications);
    }

    public function test_admin_password_change_uses_real_password_flow_and_is_audited(): void
    {
        $administrator = User::factory()->create([
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($administrator)
            ->from(route('admin.settings'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'a-secure-new-password',
                'password_confirmation' => 'a-secure-new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.settings'));

        $this->assertTrue(Hash::check('a-secure-new-password', $administrator->refresh()->password));
        $audit = AuditLog::query()->where('action', AuditLogger::PASSWORD_CHANGED)->sole();
        $this->assertSame($administrator->id, $audit->user_account_id);
        $this->assertSame('Authentication', $audit->module);
        $this->assertStringNotContainsString('a-secure-new-password', (string) $audit->description);
    }

    public function test_active_session_review_uses_non_sensitive_session_metadata(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $this->app->instance(ActiveSessionCatalog::class, new class extends ActiveSessionCatalog
        {
            public function sessions(Request $request, User $user): Collection
            {
                return collect([
                    [
                        'id' => 'hashed-current-id',
                        'device' => 'Chrome on macOS',
                        'current' => true,
                        'lastActiveLabel' => 'Active now',
                        'lastActiveIso' => '2026-08-14T09:00:00+08:00',
                        'lastActiveTitle' => 'August 14, 2026 at 09:00 AM',
                    ],
                    [
                        'id' => 'hashed-other-id',
                        'device' => 'Safari on iOS',
                        'current' => false,
                        'lastActiveLabel' => 'Last active 2 hours ago',
                        'lastActiveIso' => '2026-08-14T07:00:00+08:00',
                        'lastActiveTitle' => 'August 14, 2026 at 07:00 AM',
                    ],
                ]);
            }
        });

        $this->actingAs($administrator)
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('You are signed in on 2')
            ->assertSee('devices.')
            ->assertSee('Chrome on macOS')
            ->assertSee('Safari on iOS')
            ->assertSee('Read only')
            ->assertDontSee('192.168.')
            ->assertDontSee('session payload');
    }

    public function test_session_provider_failure_keeps_settings_available(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $this->app->instance(ActiveSessionCatalog::class, new class extends ActiveSessionCatalog
        {
            public function sessions(Request $request, User $user): Collection
            {
                throw new RuntimeException('private-session-storage-path');
            }
        });

        $this->actingAs($administrator)
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('Other session records are temporarily unavailable.')
            ->assertSee('Other session records could not be loaded.')
            ->assertDontSee('private-session-storage-path');
    }
}
