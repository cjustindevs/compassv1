<?php

namespace Tests\Feature;

use App\Models\EmergencyResource;
use App\Models\HelpSeeker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The seeker emergency destination is the target of the most urgent action in
 * the product, so it has to carry the same weight as the rest of the seeker UI
 * and it must never imply COMPASS is an emergency service.
 */
class SeekerEmergencyPageTest extends TestCase
{
    use RefreshDatabase;

    private function seeker(): User
    {
        $user = User::factory()->create(['role' => 'seeker']);

        // The `seeker-workflow` gate also requires a HelpSeeker profile record.
        HelpSeeker::create([
            'user_account_id' => $user->id,
            'generated_alias' => 'CalmFox'.$user->id,
            'age' => 20,
            'gender' => 'prefer-not-to-say',
        ]);

        return $user;
    }

    /**
     * Blade wraps the safety copy across source lines, so collapse whitespace
     * before asserting on a sentence rather than on a rendered line.
     */
    private function flat(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', $html));
    }

    public function test_guests_are_sent_to_the_shared_login(): void
    {
        $this->get(route('emergency'))->assertRedirect(route('login'));
    }
    public function test_page_leads_with_the_immediate_danger_escalation(): void
    {
        $response = $this->actingAs($this->seeker())->get(route('emergency'));

        $response->assertOk();

        $flat = $this->flat($response->getContent());

        $this->assertStringContainsString('Emergency support', $flat);
        $this->assertStringContainsString('If you are in danger right now', $flat);
        $this->assertStringContainsString('Call your local emergency number', $flat);
        // Stated up front, not buried at the bottom.
        $this->assertStringContainsString('cannot promise an immediate response', $flat);
    }

    public function test_page_never_claims_compass_is_an_emergency_service(): void
    {
        $response = $this->actingAs($this->seeker())->get(route('emergency'));

        $response->assertOk();

        $flat = $this->flat($response->getContent());

        $this->assertStringContainsString('does not replace emergency or professional services', $flat);
        $this->assertStringContainsString(
            'COMPASS peer sessions are not monitored continuously and are not an emergency service',
            $flat
        );
        $this->assertStringNotContainsString('COMPASS also offers emergency services', $flat);
    }

    public function test_published_hotlines_render_as_callable_cards(): void
    {
        EmergencyResource::create([
            'agency_name' => 'National Crisis Line',
            'hotline' => '1553 (landline)',
            'description' => 'Free, confidential crisis support.',
        ]);

        $response = $this->actingAs($this->seeker())->get(route('emergency'));

        $response->assertOk()
            ->assertSee('Crisis hotlines')
            ->assertSee('National Crisis Line')
            ->assertSee('Free, confidential crisis support.')
            // The tel: href strips formatting so the dialer works on mobile.
            ->assertSee('href="tel:1553"', false)
            ->assertSee('1553 (landline)');
    }

    public function test_unpublished_hotlines_are_not_exposed(): void
    {
        EmergencyResource::create([
            'agency_name' => 'Retired Line',
            'hotline' => '0917-000-0000',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($this->seeker())->get(route('emergency'));

        $response->assertOk()->assertDontSee('Retired Line');
    }

    public function test_empty_state_points_somewhere_useful_when_no_hotlines_are_published(): void
    {
        $response = $this->actingAs($this->seeker())->get(route('emergency'));

        $response->assertOk()
            ->assertSee('No published contacts are available right now')
            ->assertSee('Use your local emergency service')
            // Still offers the things COMPASS can actually do.
            ->assertSee('Talk to a peer helper')
            ->assertSee('Use the self-help library')
            ->assertSee(route('selfhelp'), false)
            ->assertSee(route('request.screening'), false);
    }

    public function test_emergency_nav_item_is_highlighted_while_on_the_page(): void
    {
        $response = $this->actingAs($this->seeker())->get(route('emergency'));

        $response->assertOk();

        $this->assertMatchesRegularExpression(
            '/<a href="'.preg_quote(route('emergency'), '/').'"\s+class="nav-item active">/',
            $response->getContent(),
            'The Emergency rail entry should be highlighted on the emergency page.'
        );
    }

    public function test_screening_banner_points_at_emergency_without_overclaiming(): void
    {
        $content = $this->flat(
            $this->actingAs($this->seeker())->get(route('request.screening'))->assertOk()->getContent()
        );

        $this->assertStringContainsString(
            'COMPASS is not an emergency service',
            $content,
            'The screening banner must not imply COMPASS provides emergency response.'
        );
        $this->assertStringContainsString('Emergency support', $content);
    }

    /**
     * request.matching redirects to screening unless the seeker already has a
     * session, so the banner is pinned at the source level instead of paying
     * for a full workflow fixture. This is what stops the two banners drifting.
     */
    public function test_matching_banner_copy_matches_the_screening_banner(): void
    {
        $banner = fn (string $view) => $this->flat($this->bannerMarkup($view));

        $this->assertSame(
            $banner('screening'),
            $banner('matching'),
            'The emergency banner copy must be identical on screening and matching.'
        );

        $this->assertStringContainsString('COMPASS is not an emergency service', $banner('matching'));
        $this->assertStringNotContainsString('Click the Emergency button in the sidebar', $banner('matching'));
    }

    private function bannerMarkup(string $view): string
    {
        $source = file_get_contents(resource_path("views/request/{$view}.blade.php"));

        // From the banner heading through the closing call-to-action link.
        $this->assertSame(
            1,
            preg_match('/<p class="text-sm font-medium text-red-700">Need immediate help\?<\/p>.*?<\/a>/s', $source, $matches),
            "Expected exactly one emergency banner in the [{$view}] view."
        );

        return $matches[0];
    }
}
