<?php

namespace App\Http\Controllers;

use App\Models\EmergencyAlert;
use App\Models\Notification;
use App\Services\EmergencyNoticeState;
use App\Services\ModeratorEmergencyCases;
use App\Services\NotificationPopupDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public const TYPES = [
        'session' => ['label' => 'Sessions', 'icon' => 'fa-comments'],
        'assignment' => ['label' => 'Assignments', 'icon' => 'fa-clipboard-list'],
        'referral' => ['label' => 'Referrals', 'icon' => 'fa-pen-to-square'],
        'evaluation' => ['label' => 'Evaluations', 'icon' => 'fa-chart-column'],
        'emergency' => ['label' => 'Emergencies', 'icon' => 'fa-triangle-exclamation'],
        'reminder' => ['label' => 'Reminders', 'icon' => 'fa-clock'],
        'system' => ['label' => 'System', 'icon' => 'fa-bell'],
        'update' => ['label' => 'Updates', 'icon' => 'fa-star'],
    ];

    /**
     * Notification center with type filtering
     */
    public function index(Request $request): View
    {
        $type = $request->query('type');
        abort_unless($type === null || array_key_exists($type, static::TYPES), 404);

        $notifications = Notification::where('user_account_id', Auth::id())
            ->ofType($type)
            ->latest()->latest('id')
            ->paginate(15)->withQueryString();

        $unreadCount = Notification::where('user_account_id', Auth::id())
            ->unread()
            ->count();

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'activeType' => $type,
            'types' => static::TYPES,
        ]);
    }

    /**
     * Mark a single notification as read
     */
    public function markAsRead(int $id): RedirectResponse|JsonResponse
    {
        $notification = Notification::where('user_account_id', Auth::id())
            ->findOrFail($id);

        $notification->markAsRead();

        if (request()->expectsJson()) {
            return response()->json(['read' => true]);
        }

        return $notification->link
            ? redirect($notification->link)
            : back();
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): RedirectResponse|JsonResponse
    {
        Notification::where('user_account_id', Auth::id())
            ->unread()
            ->update([
                'status' => 'read',
                'read_at' => now(),
            ]);

        if (request()->expectsJson()) {
            return response()->json(['read_all' => true]);
        }

        return back()->with('success', 'All notifications marked as read.');
    }

    /**
     * Archive a notification
     */
    public function destroy(int $id): RedirectResponse|JsonResponse
    {
        Notification::where('user_account_id', Auth::id())
            ->findOrFail($id)
            ->archive();

        if (request()->expectsJson()) {
            return response()->json(['archived' => true]);
        }

        return back()->with('success', 'Notification archived.');
    }

    /**
     * Unread count for the sidebar badge (AJAX polling)
     */
    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'count' => Notification::where('user_account_id', Auth::id())
                ->unread()
                ->count(),
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        $data = $request->validate(['after' => 'nullable|integer|min:0']);
        $owned = Notification::where('user_account_id', $request->user()->id);
        $delivery = app(NotificationPopupDelivery::class);
        $state = new EmergencyNoticeState;
        $staff = in_array($request->user()->role, ['moderator', 'adviser', 'professional'], true);
        $emergencyCopy = app(\App\Services\EmergencyNotificationPresentation::class);
        $present = fn ($notice) => array_merge($emergencyCopy->present($notice, $request->user()), ['id' => $notice->id,
            'type' => $notice->notification_type, 'link' => $notice->link, 'read' => $notice->is_read,
            'popup_key' => $delivery->key($notice, $request->user()),
            'present_url' => route('notifications.present', $notice->id, false),
            'important' => in_array($notice->notification_type, ['referral', 'emergency'], true)
                || ($notice->notification_type === 'assignment' && ! in_array($notice->title, ['Helper ready for duty', 'Duty scheduled', 'Duty updated', 'Duty cancelled'], true)),
            'urgent' => $staff && $notice->notification_type === 'emergency' ? $delivery->actionable($notice, $request->user()) : null]);
        $cursor = (int) (clone $owned)->max('id');
        $fresh = (clone $owned)->unread()->whereNull('popup_presented_at')
            ->where('created_at', '>=', now()->subMinutes(NotificationPopupDelivery::FRESH_MINUTES))
            ->when(isset($data['after']), fn ($q) => $q->where('id', '>', $data['after']))->orderBy('id')->limit(30)->get();
        if ($fresh->count() === 30) {
            $cursor = (int) $fresh->last()->id;
        }

        $workflow = [];
        $workflow['unread_important_notice_ids'] = (clone $owned)->unread()->whereIn('notification_type', ['emergency', 'referral', 'assignment'])->pluck('id')->values();
        if ($request->user()->is_active && $request->user()->role === 'adviser' && $adviser = $request->user()->adviser) {
            $workflow['emergency_count'] = EmergencyAlert::visibleForAdviser($adviser->id)->whereNotIn('status', ['resolved', 'closed'])->count();
        }

        if ($request->user()->role === 'moderator') {
            $workflow['emergency_count'] = app(ModeratorEmergencyCases::class)->active()->count();
        }

        if ($staff) {
            $workflow['active_emergency_notice_ids'] = (clone $owned)->where('notification_type', 'emergency')->get()
                ->filter(fn ($notice) => $delivery->actionable($notice, $request->user()))->pluck('id')->values();
        }

        // Actionable unread work must survive an old browser cursor or an interrupted page load.
        $attention = (clone $owned)->unread()->whereNull('popup_presented_at')
            ->where('created_at', '>=', now()->subMinutes(NotificationPopupDelivery::FRESH_MINUTES))
            ->whereIn('notification_type', ['referral','assignment'])
            ->whereNotIn('title', ['Helper ready for duty','Duty scheduled','Duty updated','Duty cancelled'])
            ->orderByDesc('id')->limit(30)->get()->filter(fn ($notice) => $delivery->eligible($notice, $request->user()))->map($present)->values();
        $workflow['pending_action_count'] = (clone $owned)->unread()->whereIn('notification_type', ['referral','assignment','emergency'])
            ->whereNotIn('title', ['Helper ready for duty','Duty scheduled','Duty updated','Duty cancelled'])
            ->get()->filter(fn ($notice) => $delivery->actionable($notice, $request->user()))->count();
        return response()->json(['count' => (clone $owned)->unread()->count(), 'cursor' => $cursor, 'workflow' => $workflow, 'attention'=>$attention,
            'recent' => (clone $owned)->latest('id')->limit(8)->get()->map($present), 'fresh' => $fresh->map($present)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function presentPopup(int $id, Request $request): JsonResponse
    {
        return response()->json(['present' => app(NotificationPopupDelivery::class)->claim($id, $request->user())])
            ->header('Cache-Control', 'private, no-store');
    }
}
