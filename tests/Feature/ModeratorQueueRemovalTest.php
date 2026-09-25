<?php

namespace Tests\Feature;

use App\Models\{HelpSeeker, Notification, QueueRequest, Session, User};
use App\Services\ModeratorQueueRemoval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModeratorQueueRemovalTest extends TestCase
{
    use RefreshDatabase;

    private function requestFixture(string $status = 'waiting'): array
    {
        $user = User::factory()->create(['role' => 'seeker']);
        $seeker = HelpSeeker::create(['user_account_id' => $user->id, 'generated_alias' => 'TestSeeker', 'age' => 20, 'gender' => 'male']);
        $queue = QueueRequest::create(['seeker_id' => $seeker->id, 'request_status' => $status, 'request_date' => now(), 'priority_level' => 'low', 'preferred_session_type' => 'chat']);
        $session = Session::create(['seeker_id' => $seeker->id, 'queue_request_id' => $queue->id, 'session_status' => $status === 'assigned' ? 'helper_assigned' : 'waiting', 'risk_level' => 'low', 'created_date' => now()]);
        return [$queue, $session];
    }

    public function test_browser_form_removes_waiting_request_and_redirects_with_feedback(): void
    {
        [$queue, $session] = $this->requestFixture();
        $this->actingAs(User::factory()->create(['role'=>'moderator','is_active'=>true]));
        $this->get(route('moderator.queue'))->assertOk()->assertSee('name="_method" value="DELETE"',false)->assertDontSee('data-remove-url',false);
        $this->post(route('moderator.queue.remove',$queue->id),['_method'=>'DELETE'])->assertRedirect(route('moderator.queue'))->assertSessionHas('success');
        $this->assertSame('cancelled',$session->fresh()->session_status);
        $this->post(route('moderator.queue.remove',$queue->id),['_method'=>'DELETE'])->assertRedirect(route('moderator.queue'))->assertSessionHas('error');
    }

    public function test_url_id_is_used_and_cancelled_request_cannot_be_removed_again(): void
    {
        [$queue, $session] = $this->requestFixture();
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'is_active' => true]));
        $this->deleteJson(route('moderator.queue.remove', $queue->id))->assertOk()->assertJson(['success' => true]);
        $this->assertSame('cancelled', $session->fresh()->session_status);
        $this->assertSame('cancelled', $queue->fresh()->request_status);
        $this->assertTrue(Notification::where('user_account_id', $queue->seeker->user_account_id)->exists());
        $this->deleteJson(route('moderator.queue.remove', $queue->id))->assertStatus(409);
    }

    public function test_unaccepted_assignment_is_requeued_without_deleting_session(): void
    {
        [$queue, $session] = $this->requestFixture('assigned');
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'is_active' => true]));
        app(ModeratorQueueRemoval::class)->remove($queue->id);
        $this->assertSame('waiting', $queue->fresh()->request_status);
        $this->assertSame('waiting', $session->fresh()->session_status);
        $this->assertNull($session->fresh()->cancelled_at);
    }

    public function test_started_session_is_preserved(): void
    {
        [$queue, $session] = $this->requestFixture('assigned');
        $session->update(['session_status' => 'active', 'helper_accepted_at' => now()]);
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'is_active' => true]));
        $this->deleteJson(route('moderator.queue.remove', $queue->id))->assertStatus(409);
        $this->assertSame('active', $session->fresh()->session_status);
        $this->assertSame('assigned', $queue->fresh()->request_status);
    }

    public function test_seeker_cannot_remove_queue_entry(): void
    {
        [$queue] = $this->requestFixture();
        $this->actingAs($queue->seeker->user);
        $this->deleteJson(route('moderator.queue.remove', $queue->id))->assertForbidden();
        $this->assertSame('waiting', $queue->fresh()->request_status);
    }
}
