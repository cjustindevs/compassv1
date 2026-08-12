<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public const TYPES = [
        'session' => ['label' => 'Sessions', 'icon' => '💬'],
        'reminder' => ['label' => 'Reminders', 'icon' => '⏰'],
        'system' => ['label' => 'System', 'icon' => '🔔'],
        'update' => ['label' => 'Updates', 'icon' => '🎉'],
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
            ->latest()
            ->limit(60)
            ->get();

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
     * Delete a notification
     */
    public function destroy(int $id): RedirectResponse|JsonResponse
    {
        Notification::where('user_account_id', Auth::id())
            ->findOrFail($id)
            ->delete();

        if (request()->expectsJson()) {
            return response()->json(['deleted' => true]);
        }

        return back()->with('success', 'Notification deleted.');
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
}