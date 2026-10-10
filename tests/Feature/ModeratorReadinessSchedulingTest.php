<?php

namespace Tests\Feature;

use App\Models\Helper;
use App\Models\Moderator;
use App\Models\ReadinessCheck;
use App\Models\User;
use App\Services\HelperReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ModeratorReadinessSchedulingTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\SeekerWorkflowFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-10 20:00', 'Asia/Manila')->utc());
        config(['session.driver' => 'database', 'session.table' => 'sessions']);
    }

    private function moderator(): User
    {
        $user = User::factory()->create(['role' => 'moderator', 'is_active' => true]);
        Moderator::create(['user_account_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'Moderator', 'email' => $user->email]);
        return $user;
    }

    private function helper(): Helper
    {
        $user = User::factory()->create(['role' => 'helper', 'is_active' => true]);
        $helper = Helper::create(['user_account_id' => $user->id, 'first_name' => 'Ready', 'last_name' => 'Helper', 'email' => $user->email, 'status' => 'offline', 'availability' => 'unavailable']);
        $this->verifiedHelperFixture($helper);
        DB::table('sessions')->insert(['id' => 'helper-login-'.$helper->id, 'user_id' => $user->id, 'last_activity' => now()->timestamp, 'payload' => '']);
        return $helper;
    }

    private function passReadiness(Helper $helper): void
    {
        $this->actingAs($helper->user)->post(route('helper.readiness.store'), [
            'emotionally_ready' => true, 'willing_to_listen' => true, 'stress_level' => 'low',
            'availability_status' => 'available', 'exercise_completed' => 'skipped',
            'skills_confirmed' => HelperReadinessService::SKILLS,
        ])->assertRedirect(route('helper.dashboard'))->assertSessionHasNoErrors();
    }

    public function test_ready_helper_can_be_given_first_duty_even_with_available_filter(): void
    {
        $helper = $this->helper();
        $this->passReadiness($helper);
        $this->assertSame('offline', $helper->fresh()->status);
        $this->assertDatabaseCount('helper_schedules', 0);

        $page = $this->actingAs($this->moderator())->get(route('moderator.schedules', ['availability' => 'available']))
            ->assertOk()->assertViewHas('dutyHelpers', fn ($helpers) => $helpers->contains('id', $helper->id));
        if (getenv('COMPASS_UI_CAPTURE')) {
            $directory = base_path('.ui-preview');
            if (! is_dir($directory)) mkdir($directory, 0777, true);
            file_put_contents($directory.'/moderator-schedules.html', $page->getContent());
        }

        $this->post(route('moderator.schedules.store'), ['helper_id' => $helper->id, 'event_date' => now('Asia/Manila')->toDateString()])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('helper_schedules', ['helper_id' => $helper->id, 'is_active' => true]);
        $this->assertSame('available', $helper->fresh()->status);
    }

    public function test_live_candidates_follow_readiness_submission_failure_expiry_and_logout(): void
    {
        $helper = $this->helper();
        $moderator = $this->moderator();
        $url = '/moderator/schedules/helpers';
        $this->actingAs($moderator)->getJson($url)->assertOk()->assertJsonCount(0, 'helpers');
        $this->passReadiness($helper);
        $this->actingAs($moderator)->getJson($url)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('helpers.0.id', $helper->id)->assertJsonPath('helpers.0.name', $helper->full_name);

        $check = $helper->readinessChecks()->latest('id')->first();
        $check->update(['valid_until' => now()->subMinute()]);
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'helpers');
        $check->update(['valid_until' => now()->addHour()]);
        DB::table('sessions')->where('user_id', $helper->user_account_id)->delete();
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'helpers');
        DB::table('sessions')->insert(['id' => 'new-helper-login', 'user_id' => $helper->user_account_id, 'last_activity' => now()->timestamp, 'payload' => '']);
        ReadinessCheck::create(['helper_id' => $helper->id, 'assessment_date' => now(), 'valid_until' => now()->addHour(), 'assessment_result' => 'not_ready', 'is_active' => true]);
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'helpers');
        $this->from(route('moderator.schedules'))->post(route('moderator.schedules.store'), ['helper_id' => $helper->id, 'event_date' => now('Asia/Manila')->toDateString()])
            ->assertSessionHasErrors('helper_id');
        $this->assertDatabaseCount('helper_schedules', 0);
    }

    public function test_candidate_endpoint_is_only_accessible_to_moderators(): void
    {
        $helper = $this->helper();
        $this->actingAs($helper->user)->getJson('/moderator/schedules/helpers')->assertForbidden();
        $helper->user->update(['role' => 'moderator', 'is_active' => false]);
        $this->actingAs($helper->user->fresh())->getJson('/moderator/schedules/helpers')->assertForbidden();
    }
}
