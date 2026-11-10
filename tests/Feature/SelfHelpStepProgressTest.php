<?php

namespace Tests\Feature;

use App\Models\HelpSeeker;
use App\Models\SelfHelpResource;
use App\Models\User;
use App\Models\UserResourceProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelfHelpStepProgressTest extends TestCase
{
    use RefreshDatabase;

    private function seeker(): User
    {
        $user = User::factory()->create(['role' => 'seeker']);
        HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'StepBay'.$user->id, 'age' => 20, 'gender' => 'prefer-not-to-say']);

        return $user;
    }

    public function test_resource_page_splits_content_into_steps_with_a_progress_bar(): void
    {
        $resource = SelfHelpResource::create(['title' => 'Box Breathing', 'category' => 'exercise', 'tags' => ['breathing'], 'is_published' => true,
            'content' => "Intro line.\n\n## Get comfortable\n\nSit upright.\n\n## Breathe in\n\nCount to four."]);

        $this->actingAs($this->seeker())->get(route('selfhelp.show', $resource->id))->assertOk()
            ->assertSee('Step 1 of 3')->assertSee('Get comfortable')->assertSee('Breathe in')
            ->assertSee('role="progressbar"', false)->assertSee('data-listen', false)->assertSee('data-calm-sound', false)
            ->assertSee('Breathe with the circle')->assertSee('Mark complete');
    }

    public function test_step_progress_only_moves_forward_and_never_clears_completion(): void
    {
        $user = $this->seeker();
        $resource = SelfHelpResource::create(['title' => 'Stress Steps', 'category' => 'exercise', 'is_published' => true, 'content' => '## One']);

        $this->actingAs($user)->postJson(route('selfhelp.progress', $resource->id), ['percentage' => 60])->assertOk()->assertJson(['percentage' => 60]);
        $this->postJson(route('selfhelp.progress', $resource->id), ['percentage' => 20])->assertOk()->assertJson(['percentage' => 60]);
        $this->postJson(route('selfhelp.complete', $resource->id))->assertOk();
        $this->postJson(route('selfhelp.progress', $resource->id), ['percentage' => 30])->assertOk()->assertJson(['percentage' => 100, 'completed' => true]);

        $progress = UserResourceProgress::where(['user_id' => $user->id, 'resource_id' => $resource->id])->first();
        $this->assertSame(100, $progress->progress_percentage);
        $this->assertNotNull($progress->completed_at);
    }
}
