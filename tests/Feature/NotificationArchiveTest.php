<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create(['role' => 'seeker']);
    }

    private function makeNotification(User $user, array $overrides = []): Notification
    {
        return Notification::create(array_merge([
            'user_account_id' => $user->id,
            'title' => 'Test notification',
            'message' => 'Notification body',
            'notification_type' => 'system',
            'status' => 'unread',
        ], $overrides));
    }

    public function test_archived_notification_can_be_restored_to_inbox(): void
    {
        $user = $this->makeUser();
        $notification = $this->makeNotification($user);
        $notification->archive();

        $this->assertNotNull($notification->fresh()->archived_at);

        $this->actingAs($user)
            ->post(route('notifications.restore', $notification->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNull($notification->fresh()->archived_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'notification_restored']);
    }

    public function test_restore_is_scoped_to_own_notifications(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $notification = $this->makeNotification($owner);
        $notification->archive();

        $this->actingAs($other)
            ->post(route('notifications.restore', $notification->id))
            ->assertNotFound();

        $this->assertNotNull($notification->fresh()->archived_at);
    }

    public function test_auto_archive_preference_is_saved(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->post(route('notifications.auto-archive'), ['auto_archive_read_days' => 30])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(30, $user->fresh()->auto_archive_read_days);

        $this->actingAs($user)
            ->post(route('notifications.auto-archive'), ['auto_archive_read_days' => 0])
            ->assertRedirect();

        $this->assertNull($user->fresh()->auto_archive_read_days);

        $this->actingAs($user)
            ->post(route('notifications.auto-archive'), ['auto_archive_read_days' => 5])
            ->assertSessionHasErrors('auto_archive_read_days');
    }

    public function test_retention_service_archives_only_stale_read_notifications_for_opted_in_users(): void
    {
        $optedIn = $this->makeUser();
        $optedIn->update(['auto_archive_read_days' => 30]);

        $staleRead = $this->makeNotification($optedIn, ['status' => 'read', 'read_at' => now()->subDays(40)]);
        $recentRead = $this->makeNotification($optedIn, ['status' => 'read', 'read_at' => now()->subDays(2)]);
        $staleUnread = $this->makeNotification($optedIn, ['status' => 'unread', 'read_at' => now()->subDays(40)]);

        $otherUser = $this->makeUser();
        $otherStaleRead = $this->makeNotification($otherUser, ['status' => 'read', 'read_at' => now()->subDays(40)]);

        app(NotificationRetentionService::class)->run();

        $this->assertNotNull($staleRead->fresh()->archived_at);
        $this->assertNull($recentRead->fresh()->archived_at);
        $this->assertNull($staleUnread->fresh()->archived_at);
        $this->assertNull($otherStaleRead->fresh()->archived_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'notifications_auto_archived']);
    }

    public function test_archive_page_shows_preference_and_history(): void
    {
        $user = $this->makeUser();
        $archived = $this->makeNotification($user);
        $archived->archive();
        $user->update(['auto_archive_read_days' => 7]);

        $this->actingAs($user)
            ->get(route('notifications.archive'))
            ->assertOk()
            ->assertSee('Archive preference')
            ->assertSee('Test notification')
            ->assertSee('href="'.route('notifications').'"', false)
            ->assertSee('Restore to inbox');
    }

    public function test_inbox_keeps_filters_pagination_and_owned_read_archive_actions(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();
        $foreign = $this->makeNotification($other, ['title' => 'Private other-user update']);
        for ($index = 0; $index < 16; $index++) {
            $this->makeNotification($user, ['title' => 'Owned system update '.$index]);
        }
        $session = $this->makeNotification($user, ['title' => 'Owned session update', 'notification_type' => 'session', 'link' => '/session/history']);

        $this->actingAs($user)->get(route('notifications'))->assertOk()
            ->assertViewHas('notifications', fn ($items) => $items->total() === 17 && $items->count() === 15)
            ->assertDontSee($foreign->title);
        $this->get(route('notifications', ['page' => 2]))->assertOk()
            ->assertViewHas('notifications', fn ($items) => $items->count() === 2);
        $this->get(route('notifications', ['type' => 'session']))->assertOk()
            ->assertViewHas('notifications', fn ($items) => $items->total() === 1)
            ->assertSee($session->title)->assertSee("markRead({$session->id}, true)", false)->assertSeeText('Open');
        $this->postJson(route('notifications.read', $session->id))->assertOk()->assertJson(['read' => true]);
        $this->assertTrue($session->fresh()->is_read);
        $this->postJson(route('notifications.read', $foreign->id))->assertNotFound();
        $this->deleteJson(route('notifications.destroy', $foreign->id))->assertNotFound();
        $this->deleteJson(route('notifications.destroy', $session->id))->assertOk()->assertJson(['archived' => true]);
        $this->assertDatabaseHas('notifications', ['id' => $session->id]);
        $this->assertNotNull(Notification::withoutGlobalScopes()->findOrFail($session->id)->archived_at);
        $this->get(route('notifications', ['type' => 'session']))->assertOk()->assertDontSee($session->title);
    }

    public function test_notification_center_filters_by_read_state_and_type_from_one_compact_toolbar(): void
    {
        // QA Nov 24: nine type tabs crowded the page; filters, list and actions are arranged in one toolbar and panel.
        $user = $this->makeUser();
        $unread = $this->makeNotification($user, ['title' => 'Unread reminder', 'notification_type' => 'reminder']);
        $read = $this->makeNotification($user, ['title' => 'Already read update', 'notification_type' => 'update', 'status' => 'read', 'read_at' => now()]);

        $html = $this->actingAs($user)->get(route('notifications'))->assertOk()->getContent();
        $this->assertStringContainsString('class="notif-toolbar"', $html);
        $this->assertStringContainsString('<select id="notifType" name="type"', $html);
        $this->assertStringNotContainsString('class="filter-btn', $html, 'The row of nine type tabs is gone.');
        $this->assertStringContainsString('class="notif-panel"', $html);

        $this->get(route('notifications', ['status' => 'unread']))->assertSee('Unread reminder')->assertDontSee('Already read update');
        $this->get(route('notifications', ['status' => 'unread', 'type' => 'update']))->assertDontSee('Already read update');
        $this->get(route('notifications', ['status' => 'everything']))->assertNotFound();
    }
}
