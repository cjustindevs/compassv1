<?php

namespace Tests\Feature;

use App\Http\Controllers\SelfHelpController;
use App\Models\HelpSeeker;
use App\Models\SelfHelpResource;
use App\Models\User;
use App\Models\UserResourceProgress;
use App\Models\UserSavedResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The Self-Help Tools and mood check-in were redesigned to be text-only: no
 * emojis, no icon font, no icon-only affordances. These tests lock in the
 * organising behaviour of the library and guard the two constraints that are
 * easy to regress: the no-icons rule, and the fact that the mood check-in is a
 * purely client-side reflection that must not start persisting or classifying.
 */
class SelfHelpToolsPresentationTest extends TestCase
{
    use RefreshDatabase;

    private function seeker(): User
    {
        $user = User::factory()->create(['role' => 'seeker']);

        HelpSeeker::create([
            'user_account_id' => $user->id,
            'generated_alias' => 'CalmBay'.$user->id,
            'age' => 20,
            'gender' => 'prefer-not-to-say',
        ]);

        return $user;
    }

    private function resource(array $overrides = []): SelfHelpResource
    {
        return SelfHelpResource::create(array_merge([
            'title' => 'A resource',
            'description' => 'Some description.',
            'category' => 'article',
            'content' => "## Heading\n\nBody copy.",
            'is_published' => true,
        ], $overrides));
    }

    /**
     * Anything matching this pattern would reintroduce an icon or an emoji into
     * one of the redesigned surfaces.
     */
    private function assertNoIconsOrEmoji(string $html, string $context): void
    {
        $this->assertDoesNotMatchRegularExpression('/<i\s+class=/i', $html, "{$context}: <i> icon markup found");
        $this->assertDoesNotMatchRegularExpression('/\bfa[srb]?\s+fa-/i', $html, "{$context}: Font Awesome class found");
        $this->assertDoesNotMatchRegularExpression('/font-?awesome/i', $html, "{$context}: Font Awesome stylesheet found");
        $this->assertDoesNotMatchRegularExpression('/x-ui-icon/i', $html, "{$context}: ui-icon component found");
        $this->assertDoesNotMatchRegularExpression(
            '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{1F000}-\x{1F02F}\x{FE0F}]/u',
            $html,
            "{$context}: emoji found"
        );
    }

    public function test_dashboard_groups_resources_into_named_text_sections(): void
    {
        $this->resource(['title' => 'Five Minute Breathing Routine', 'category' => 'exercise', 'tags' => ['breathing', 'calm']]);
        $this->resource(['title' => 'Grounding Through the Senses', 'category' => 'exercise', 'tags' => ['grounding']]);
        $this->resource(['title' => 'Understanding Academic Burnout', 'tags' => ['stress', 'burnout']]);
        $this->resource(['title' => 'Guided Body Scan', 'category' => 'meditation', 'tags' => ['meditation']]);
        $this->resource(['title' => 'Mood Journal', 'category' => 'tool', 'tags' => ['journal']]);
        $this->resource(['title' => 'Crisis Coping Steps', 'tags' => ['coping', 'crisis']]);

        $response = $this->actingAs($this->seeker())->get(route('selfhelp'))->assertOk();

        foreach ([
            'Breathing Exercises',
            'Grounding Techniques',
            'Stress Management',
            'Mindfulness',
            'Journaling',
            'Coping Strategies',
        ] as $label) {
            $response->assertSee($label);
        }

        $response->assertDontSee('More Resources');
    }

    public function test_unclassified_resources_are_still_shown_in_more_resources(): void
    {
        $this->resource(['title' => 'Zzz Unmatched Topic', 'tags' => ['zzz-unmatched']]);

        $this->actingAs($this->seeker())
            ->get(route('selfhelp'))
            ->assertOk()
            ->assertSee('More Resources')
            ->assertSee('Zzz Unmatched Topic');
    }

    public function test_every_published_resource_is_listed_exactly_once_across_sections(): void
    {
        $titles = [
            'Breathing Basics', 'Grounding Steps', 'Burnout Recovery',
            'Meditation Practice', 'Journaling Prompts', 'Coping Plan',
            'Sleep Routine', 'Social Confidence', 'Unmatched Extra',
        ];

        foreach ($titles as $title) {
            $this->resource(['title' => $title]);
        }

        $html = $this->actingAs($this->seeker())->get(route('selfhelp'))->assertOk()->getContent();

        foreach ($titles as $title) {
            $this->assertSame(
                1,
                substr_count($html, '>'.$title.'<'),
                "{$title} should appear exactly once in the sectioned listing"
            );
        }
    }

    public function test_selfhelp_pages_render_without_icons_or_emoji(): void
    {
        $user = $this->seeker();

        // An emoji is stored in the legacy `icon` column; the redesigned views
        // must not render it even though the record is untouched.
        $resource = $this->resource([
            'title' => 'Grounding Techniques',
            'category' => 'exercise',
            'icon' => "\u{1FAA8}",
            'tags' => ['grounding'],
            'duration' => '8 min',
        ]);

        $pages = [
            'index' => route('selfhelp'),
            'category' => route('selfhelp.category', 'exercise'),
            'resource' => route('selfhelp.show', $resource->id),
        ];

        foreach ($pages as $name => $url) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();
            $this->assertNoIconsOrEmoji($this->mainContent($html), "selfhelp.{$name}");
        }

        // The stored emoji survives in the database; only the rendering changed.
        $this->assertSame("\u{1FAA8}", $resource->fresh()->icon);
    }

    public function test_selfhelp_keeps_the_shared_icon_font_for_the_sidebar_chrome(): void
    {
        // The sidebar, bottom nav, and confirmation modal are shared by every
        // module and still use icon classes, so the self-help pages must keep
        // loading the icon font even though their own content has no icons.
        $resource = $this->resource(['category' => 'exercise']);

        foreach ([route('selfhelp'), route('selfhelp.show', $resource->id)] as $url) {
            $html = $this->actingAs($this->seeker())->get($url)->assertOk()->getContent();

            $this->assertMatchesRegularExpression('/font-?awesome/i', $html);
            $this->assertMatchesRegularExpression('/id="sidebarToggle"/', $html);
        }
    }

    public function test_format_filter_route_contract_is_preserved(): void
    {
        $user = $this->seeker();

        foreach (array_keys(SelfHelpController::CATEGORIES) as $slug) {
            $this->actingAs($user)->get(route('selfhelp.category', $slug))->assertOk();
        }

        $this->actingAs($user)->get(route('selfhelp.category', 'not-a-format'))->assertNotFound();
    }

    public function test_save_progress_and_complete_workflow_still_persists(): void
    {
        $user = $this->seeker();
        $resource = $this->resource(['category' => 'exercise', 'tags' => ['breathing']]);

        $this->actingAs($user)
            ->post(route('selfhelp.save', $resource->id))
            ->assertRedirect();
        $this->assertDatabaseHas('user_saved_resources', [
            'user_id' => $user->id,
            'resource_id' => $resource->id,
        ]);

        $this->actingAs($user)
            ->from(route('selfhelp.show', $resource->id))
            ->post(route('selfhelp.progress', $resource->id), ['percentage' => 40])
            ->assertRedirect();
        $this->assertDatabaseHas('user_resource_progress', [
            'user_id' => $user->id,
            'resource_id' => $resource->id,
            'progress_percentage' => 40,
        ]);

        $this->actingAs($user)
            ->postJson(route('selfhelp.complete', $resource->id))
            ->assertOk()
            ->assertJson(['completed' => true]);

        $progress = UserResourceProgress::where([
            'user_id' => $user->id,
            'resource_id' => $resource->id,
        ])->first();

        $this->assertSame(100, $progress->progress_percentage);
        $this->assertNotNull($progress->completed_at);

        $this->actingAs($user)->post(route('selfhelp.unsave', $resource->id))->assertRedirect();
        $this->assertSame(0, UserSavedResource::where([
            'user_id' => $user->id,
            'resource_id' => $resource->id,
        ])->count());
    }

    public function test_progress_endpoint_still_rejects_out_of_range_values(): void
    {
        $user = $this->seeker();
        $resource = $this->resource(['category' => 'exercise']);

        $this->actingAs($user)
            ->post(route('selfhelp.progress', $resource->id), ['percentage' => 150])
            ->assertSessionHasErrors('percentage');
    }

    public function test_selfhelp_remains_reachable_as_the_referral_decline_destination(): void
    {
        // A declined referral notifies the seeker with a '/selfhelp' link, so
        // this page must stay available and must not require extra state.
        $this->actingAs($this->seeker())
            ->get('/selfhelp')
            ->assertOk()
            ->assertSee('Self-Help Tools');
    }

    public function test_mood_check_in_is_text_only_and_does_not_persist(): void
    {
        $user = $this->seeker();

        $html = $this->actingAs($user)->get(route('seeker.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('How are you feeling today?', $html);
        $this->assertNoIconsOrEmoji($this->moodSection($html), 'seeker dashboard mood check-in');

        // Approved labels, presented as text.
        foreach (['Very Good', 'Good', 'Okay', 'Low', 'Very Low'] as $label) {
            $this->assertStringContainsString($label, $this->moodSection($html));
        }

        // The check-in must stay a private, client-side reflection: no form
        // posts, no mood field, and nothing written to the database.
        $mood = $this->moodSection($html);
        $this->assertStringNotContainsString('<form', $mood);
        $this->assertStringNotContainsString('name="mood"', $mood);
    }

    public function test_rendering_the_mood_check_in_writes_nothing_to_the_database(): void
    {
        $user = $this->seeker();

        // Warm any lazily-created rows first, then snapshot.
        $this->actingAs($user)->get(route('seeker.dashboard'))->assertOk();
        $before = $this->rowCountSnapshot();

        $this->actingAs($user)->get(route('seeker.dashboard'))->assertOk();
        $this->assertSame($before, $this->rowCountSnapshot());
    }

    public function test_mood_check_in_does_not_classify_or_route_to_emergency_services(): void
    {
        $html = $this->actingAs($this->seeker())->get(route('seeker.dashboard'))->assertOk()->getContent();
        $mood = $this->moodSection($html);

        // No triage language and no escalation away from the seeker dashboard.
        foreach (['crisis', 'emergency', '911', 'hotline', 'suicide', 'risk'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase(
                $forbidden,
                $mood,
                "mood check-in should not contain '{$forbidden}'"
            );
        }
    }

    /**
     * Isolate a page's own <main> content. The sidebar, bottom nav, offline
     * banner and confirmation modal are shared app chrome that every module
     * renders and that is out of scope for this redesign, so icon assertions
     * are applied to the redesigned region only.
     */
    private function mainContent(string $html): string
    {
        $start = strpos($html, '<main');
        $end = strpos($html, '</main>');

        $this->assertNotFalse($start, 'No <main> region found');
        $this->assertNotFalse($end, 'No closing </main> found');

        return substr($html, $start, $end - $start);
    }

    /**
     * Isolate the mood check-in block so the rest of the seeker dashboard
     * (which legitimately still uses an icon font) does not skew assertions.
     */
    private function moodSection(string $html): string
    {
        $start = strpos($html, '<!-- MOOD CHECK-IN -->');
        $end = strpos($html, '<!-- ═══════ QUOTE');

        $this->assertNotFalse($start, 'Mood check-in block not found');

        return $end === false
            ? substr($html, $start)
            : substr($html, $start, $end - $start);
    }

    /**
     * Total row count across every table, so we can assert that rendering a
     * page persists nothing anywhere rather than checking one table at a time.
     *
     * @return array<string, int>
     */
    private function rowCountSnapshot(): array
    {
        $snapshot = [];

        foreach (Schema::getTableListing() as $table) {
            try {
                $snapshot[$table] = DB::table($table)->count();
            } catch (\Throwable) {
                continue;
            }
        }

        return $snapshot;
    }
}
