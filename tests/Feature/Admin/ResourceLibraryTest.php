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

    public function test_retired_admin_resource_page_is_not_exposed(): void
    {
        $this->get('/admin/resource-library')->assertNotFound();
        foreach (['admin', 'helper', 'seeker'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/admin/resource-library')->assertNotFound();
        }
    }

    public function test_shared_published_resources_remain_available(): void
    {
        $user = User::factory()->create(['role' => 'seeker']);
        $published = $this->resource(['title' => 'Guided Grounding']);
        $draft = $this->resource(['title' => 'Unpublished Draft', 'is_published' => false]);
        $this->actingAs($user)->get(route('selfhelp'))->assertOk()->assertSee($published->title)->assertDontSee($draft->title);
        $this->assertDatabaseCount('self_help_resources', 2);
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
