<?php

namespace Tests\Feature;

use App\Models\Moderator;
use App\Models\PsychologyProfessional;
use App\Models\User;
use App\Support\UiIcon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class UiStandardizationTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\SeekerWorkflowFixtures;

    public function test_all_role_screen_families_share_assets_and_outline_icons(): void
    {
        $this->seed(\Database\Seeders\HelperModuleSeeder::class);
        $moderator = User::factory()->create(['role' => 'moderator']);
        Moderator::create(['user_account_id' => $moderator->id, 'first_name' => 'Test', 'last_name' => 'Moderator', 'email' => $moderator->email]);
        $professional = User::factory()->create(['role' => 'professional']);
        PsychologyProfessional::create(['user_account_id' => $professional->id, 'first_name' => 'Test', 'last_name' => 'Professional', 'email' => $professional->email, 'is_available' => true]);
        User::factory()->create(['role' => 'admin']);
        $this->consentFixture(User::where('role', 'seeker')->firstOrFail());

        $pages = [
            'admin' => ['admin.dashboard', 'admin.users', 'admin.settings', 'admin.reports', 'admin.roles-permissions', 'admin.audit-logs', 'admin.backup-restore', 'admin.system-health'],
            'moderator' => ['moderator.dashboard', 'moderator.queue', 'moderator.emergency', 'moderator.reports', 'moderator.settings', 'moderator.sessions', 'moderator.manage', 'moderator.schedules', 'moderator.notifications'],
            'adviser' => ['adviser.dashboard', 'adviser.helpers', 'adviser.reports', 'adviser.transcripts', 'adviser.settings', 'adviser.calendar', 'adviser.schedule', 'adviser.resources', 'adviser.notifications', 'adviser.screenings', 'adviser.evaluations', 'adviser.referrals', 'adviser.emergencies'],
            'helper' => ['helper.dashboard', 'helper.cases', 'helper.calendar', 'helper.reports', 'helper.competency', 'helper.feedback', 'helper.profile', 'helper.settings', 'helper.readiness', 'helper.resources', 'helper.notifications'],
            'seeker' => ['seeker.dashboard', 'selfhelp', 'seeker.privacy', 'seeker.referrals', 'session.history', 'settings', 'settings.account', 'settings.preferences', 'settings.privacy', 'settings.appearance', 'seeker.requests', 'emergency', 'notifications', 'notifications.archive'],
            'professional' => ['professional.dashboard', 'professional.cases', 'professional.referrals', 'professional.reports', 'professional.profile', 'professional.settings'],
        ];
        foreach ($pages as $role => $routes) {
            $user = User::where('role', $role)->firstOrFail();
            foreach ($routes as $route) {
                $html = $this->actingAs($user)->get(route($route))->assertOk()->getContent();
                if (getenv('COMPASS_UI_CAPTURE') === '1') {
                    $directory = base_path('.ui-preview');
                    if (!is_dir($directory)) mkdir($directory);
                    file_put_contents($directory.'/'.$route.'.html', $html);
                }
                $this->assertSame(1, substr_count($html, 'css/compass-ui.css'), $route);
                $this->assertStringContainsString('images/compass-icons.svg', $html, $route);
                $this->assertStringNotContainsString('font-awesome/', $html, $route);
                $this->assertStringNotContainsString('cdn.tailwindcss.com', $html, $route);
                $this->assertStringContainsString('class="compass-ui', $html, $route);
                $this->assertStringNotContainsString('<x-ui-icon', $html, $route);
            }
        }
    }

    public function test_standalone_screen_documents_cannot_omit_shared_assets(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) continue;
            $source = file_get_contents($file->getPathname());
            if (!preg_match('/<!doctype html/i', $source)) continue;
            if ($file->getFilename() === 'report-export.blade.php' || str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'emails'.DIRECTORY_SEPARATOR)) continue;
            $this->assertStringContainsString("@include('partials.ui-assets')", $source, $file->getPathname());
            $this->assertStringNotContainsString('cdn.tailwindcss.com', $source, $file->getPathname());
            $this->assertStringNotContainsString('font-awesome/', $source, $file->getPathname());
            $this->assertStringNotContainsString('user-scalable=no', $source, $file->getPathname());
        }
    }

    public function test_legacy_stored_icons_render_safely_from_the_shared_sprite(): void
    {
        $this->assertSame('clock', UiIcon::glyph("\u{23F0}"));
        $this->assertSame('message', UiIcon::glyph('fas fa-comments'));
        $this->assertSame('message', UiIcon::glyph('fa-solid fa-comments'));
        $this->assertSame('info', UiIcon::glyph('<script>alert(1)</script>'));
        $html = Blade::render('<x-ui-icon value="fas fa-comments" id="send-icon" />');
        $this->assertStringContainsString('#message', $html);
        $this->assertStringContainsString('id="send-icon"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('focusable="false"', $html);
        $this->assertStringNotContainsString('<i ', $html);
        $admin = Blade::render('<x-admin.icon name="users" size="16" class="test-icon" />');
        $this->assertStringContainsString('#users', $admin);
        $this->assertStringContainsString('width="16"', $admin);
        $this->assertStringContainsString('test-icon', $admin);
    }

    public function test_authentication_keeps_alias_contract_and_usable_controls(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();
        $this->assertStringContainsString('Nickname@compass.local', $html);
        $this->assertStringContainsString('for="login-email"', $html);
        $this->assertStringContainsString('id="login-email"', $html);
        $this->assertStringContainsString('for="login-password"', $html);
        $this->assertStringContainsString('css/compass-ui.css', $html);
    }

    public function test_saved_accessibility_preferences_are_shared_without_private_profile_data(): void
    {
        $user = User::factory()->create(['role' => 'seeker', 'font_size' => 'large', 'high_contrast' => true, 'reduced_motion' => true]);
        $this->actingAs($user);
        $html = Blade::render("@include('partials.ui-assets')");
        preg_match('/name="compass-display-preferences" content="([^"]+)"/', $html, $matches);
        $this->assertSame(['font_size' => 'large', 'high_contrast' => true, 'reduced_motion' => true], json_decode(html_entity_decode($matches[1]), true));
        $this->assertStringNotContainsString($user->email, $html);
    }

    public function test_public_entry_screens_keep_navigation_and_registration_contracts(): void
    {
        $pages = ['landing' => url('/'), 'login' => route('login'), 'register' => route('register'),
            'register-seeker' => route('seeker.register'), 'forgot-password' => route('password.request'),
            'reset-password' => route('password.reset', ['token' => 'fixture-token', 'email' => 'fixture@example.test'])];
        foreach ($pages as $name => $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, 'css/compass-ui.css'), $name);
            $this->assertStringContainsString('<main', $html, $name);
            if (getenv('COMPASS_UI_CAPTURE') === '1') {
                $directory = base_path('.ui-preview');
                if (!is_dir($directory)) mkdir($directory);
                file_put_contents($directory.'/public-'.$name.'.html', $html);
            }
        }
        $landing = $this->get('/')->assertOk();
        $landing->assertSee('href="'.route('login').'"', false)->assertSee('href="'.route('register').'"', false)
            ->assertSee('aria-controls="mobileMenu"', false)->assertDontSee('href="#"', false)
            ->assertDontSee('Wellness Progress')->assertDontSee('End-to-End Encrypted');
        $registration = $this->get(route('register'))->assertOk();
        $registration->assertSee('action="'.route('seeker.onboarding.store').'"', false)
            ->assertSee('id="agree-terms-checkbox" value="1" disabled', false)
            ->assertSee('id="email-dialog"', false)->assertSee('id="nickname-reminder"', false)
            ->assertSee(json_encode(route('registration.otp.send', [], false)), false)
            ->assertSee(json_encode(route('registration.otp.verify', [], false)), false);
    }
}
