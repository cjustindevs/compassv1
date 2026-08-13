<?php

namespace Tests\Feature\Admin;

use App\Models\SelfHelpResource;
use App\Models\User;
use App\Models\UserSavedResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_the_admin_login(): void
    {
        $this->get(route('admin.resource-library'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_non_administrator_cannot_access_the_resource_library(): void
    {
        $helper = User::factory()->create(['role' => 'helper']);

        $this->actingAs($helper)
            ->get(route('admin.resource-library'))
            ->assertForbidden();
    }

    public function test_administrator_can_browse_real_published_resources_and_bookmarks(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);
        $grounding = $this->resource([
            'title' => 'Grounding Techniques for Anxiety',
            'description' => 'A practical grounding exercise.',
            'category' => 'exercise',
            'duration' => '6 min',
            'tags' => ['anxiety', 'grounding'],
        ]);
        $sleep = $this->resource([
            'title' => 'Sleep Hygiene Checklist',
            'category' => 'article',
            'duration' => '5 min',
            'tags' => ['sleep', 'health'],
        ]);
        $unpublished = $this->resource([
            'title' => 'Unreviewed Clinical Draft',
            'is_published' => false,
        ]);

        UserSavedResource::create([
            'user_id' => $administrator->id,
            'resource_id' => $grounding->id,
            'saved_at' => now(),
        ]);

        $response = $this->actingAs($administrator)
            ->get(route('admin.resource-library'));

        $response
            ->assertOk()
            ->assertSee('Resource library')
            ->assertSee('Articles, videos, and exercises curated by the counseling team.')
            ->assertSee('Search resources...')
            ->assertSee('My bookmarks')
            ->assertSee('Grounding Techniques for Anxiety')
            ->assertSee('Sleep Hygiene Checklist')
            ->assertDontSee($unpublished->title)
            ->assertSee('data-resource-category="anxiety"', false)
            ->assertSee('data-resource-type="exercise"', false)
            ->assertSee('data-resource-bookmarked="true"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee(route('selfhelp.show', $grounding->id))
            ->assertSee(route('selfhelp.save', $sleep->id))
            ->assertSee(route('selfhelp.unsave', $grounding->id))
            ->assertSee(route('admin.resource-library'));
    }

    public function test_existing_bookmark_endpoints_persist_admin_bookmarks(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);
        $resource = $this->resource(['title' => 'Guided Breathing']);

        $this->actingAs($administrator)
            ->postJson(route('selfhelp.save', $resource->id))
            ->assertOk()
            ->assertJson(['saved' => true]);

        $this->assertDatabaseHas('user_saved_resources', [
            'user_id' => $administrator->id,
            'resource_id' => $resource->id,
        ]);

        $this->actingAs($administrator)
            ->postJson(route('selfhelp.unsave', $resource->id))
            ->assertOk()
            ->assertJson(['saved' => false]);

        $this->assertDatabaseMissing('user_saved_resources', [
            'user_id' => $administrator->id,
            'resource_id' => $resource->id,
        ]);
    }

    public function test_empty_library_renders_a_contained_empty_state(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $this->actingAs($administrator)
            ->get(route('admin.resource-library'))
            ->assertOk()
            ->assertSee('No resources found')
            ->assertSee('Try changing your search or filters.')
            ->assertSee('Clear filters');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function resource(array $overrides = []): SelfHelpResource
    {
        return SelfHelpResource::create(array_merge([
            'title' => 'Support Resource',
            'description' => 'Curated support content.',
            'category' => 'article',
            'duration' => '8 min',
            'difficulty' => 'beginner',
            'tags' => ['mental health'],
            'is_featured' => false,
            'is_published' => true,
            'views_count' => 0,
            'saved_count' => 0,
        ], $overrides));
    }
}
