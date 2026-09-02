<?php

namespace App\Http\Controllers\Helper;

use App\Events\CaseAccepted;
use App\Events\CaseDeclined;
use App\Events\NewCaseAssigned;
use App\Events\NewHelperAssigned;
use App\Events\SessionUpdated;
use App\Http\Controllers\Controller;
use App\Models\Helper;
use App\Models\Notification;
use App\Models\QueueRequest;
use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HelperCaseController extends Controller
{
    /**
     * List all sessions assigned to the logged-in helper (from the database).
     */
    public function index()
    {
        $helper = Auth::user()->helper;

        $sessions = Session::with(['seeker', 'concern', 'evaluation'])
            ->where('helper_id', $helper->id)
            ->orderByDesc('created_date')
            ->get();

        $cases = $sessions->map(fn (Session $session) => [
            'id' => $session->id,
            'reference' => $session->reference_number,
            'alias' => $session->seeker->generated_alias ?? 'Unknown',
            'status' => $session->session_status,
            'status_label' => $session->status_label,
            'risk' => ucfirst($session->risk_level ?? 'Low'),
            'risk_class' => strtolower($session->risk_level ?? 'low'),
            'mode' => $session->mode_label,
            'language' => $session->seeker?->user?->preferred_language ?: 'English',
            'concern' => $session->concern->concern_name ?? 'No concern specified',
            'age' => $session->seeker?->age,
            'gender' => $session->seeker?->gender,
            'created' => $session->created_at?->format('M d, Y h:i A'),
            'waiting' => $session->created_at?->diffForHumans(),
            'rating' => $session->evaluation ? (int) round((float) $session->evaluation->overall_score) : null,
            'active' => $session->session_status === 'active',
            'pending' => $session->session_status === 'helper_assigned',
            'completed' => $session->session_status === 'completed',
        ]);

        $stats = [
            'pending' => $cases->where('pending', true)->count(),
            'active' => $cases->where('active', true)->count(),
            'completed' => $cases->where('completed', true)->count(),
        ];

        return view('helper.cases', compact('cases', 'stats'));
    }

    /**
     * Show a single assigned case with its conversation and documentation.
     */
    public function show(int $id)
    {
        $helper = Auth::user()->helper;

        $session = Session::with([
            'seeker',
            'seeker.user',
            'concern',
            'messages',
            'report',
            'evaluation',
            'callLog',
        ])
            ->where('helper_id', $helper->id)
            ->findOrFail($id);

        return view('helper.cases-show', compact('session'));
    }

    /**
     * Accept a pending helper_assigned case — mark it active.
     */
    public function accept(Request $request, int $id)
    {
        $helper = Auth::user()->helper;

        $session = Session::with(['seeker', 'helper'])
            ->where('helper_id', $helper->id)
            ->findOrFail($id);

        if ($session->session_status !== 'helper_assigned') {
            return back()->with('error', 'This case can no longer be accepted.');
        }

        $session->update([
            'session_status' => 'active',
            'start_time' => now(),
        ]);

        Helper::where('id', $helper->id)->update(['status' => 'busy']);

        // Notify the seeker that their session is now active
        if ($session->seeker) {
            Notification::create([
                'user_account_id' => $session->seeker->user_account_id,
                'title' => 'Your session has started',
                'message' => 'A helper has accepted your session. You can now start chatting.',
                'notification_type' => 'session',
                'type_icon' => '💬',
                'link' => '/session/chat',
            ]);

            // Real-time push to the seeker's browser
            try {
                broadcast(new CaseAccepted($session, $session->seeker->user_account_id));
            } catch (\Throwable $e) {
                report($e);
            }

            // Real-time status update for anyone monitoring this session
            // (seeker, helper, and any supervising adviser on the session channel).
            try {
                broadcast(new SessionUpdated($session, $session->seeker->user_account_id));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()
            ->route('helper.session.chat', ['id' => $session->id])
            ->with('success', 'Case accepted. The session is now active.');
    }

    /**
     * Decline a pending case — release it back to the queue and try to
     * match another available helper right away.
     */
    public function decline(Request $request, int $id)
    {
        $helper = Auth::user()->helper;

        $session = Session::with(['seeker'])
            ->where('helper_id', $helper->id)
            ->findOrFail($id);

        if ($session->session_status !== 'helper_assigned') {
            return back()->with('error', 'This case can no longer be declined.');
        }

        $seekerUserId = $session->seeker?->user_account_id;

        if ($session->seeker) {
            Notification::create([
                'user_account_id' => $session->seeker->user_account_id,
                'title' => 'Helper Unavailable',
                'message' => 'Your assigned helper declined the case. You will be matched with another helper or remain in the queue.',
                'notification_type' => 'session',
                'type_icon' => '⚠️',
                'link' => '/request/matching',
            ]);
        }

        $session->update([
            'helper_id' => null,
            'session_status' => 'waiting',
            'scheduled_start' => null,
        ]);

        Helper::where('id', $helper->id)->update(['status' => 'available']);

        QueueRequest::where('seeker_id', $session->seeker_id)
            ->where('request_status', 'assigned')
            ->update([
                'request_status' => 'waiting',
                'assigned_helper_id' => null,
                'matched_date' => null,
            ]);

        // Tell the seeker (in real time) that the helper declined
        if ($seekerUserId) {
            try {
                broadcast(new CaseDeclined($session, $seekerUserId));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Try to hand the case to the next available helper immediately
        $nextHelper = Helper::findAvailableForRisk($session->risk_level, $helper->id);

        if ($nextHelper) {
            $session->update([
                'helper_id' => $nextHelper->id,
                'session_status' => 'helper_assigned',
                'scheduled_start' => now(),
            ]);

            $nextHelper->update(['status' => 'busy']);

            Notification::create([
                'user_account_id' => $nextHelper->user_account_id,
                'title' => 'New case assigned',
                'message' => 'You have been assigned a new case. Please review and accept it.',
                'notification_type' => 'assignment',
                'type_icon' => '📋',
                'link' => '/helper/cases',
            ]);

            try {
                broadcast(new NewCaseAssigned($session, $nextHelper->user_account_id));
            } catch (\Throwable $e) {
                report($e);
            }

            // Tell the seeker (in real time) that a new helper is on the case
            try {
                broadcast(new NewHelperAssigned(
                    $session,
                    $session->seeker->user_account_id,
                    $nextHelper->full_name,
                    $nextHelper->competency_level
                ));
            } catch (\Throwable $e) {
                report($e);
            }

            return redirect()->route('helper.cases')
                ->with('success', 'Case declined. Another available helper has been matched to it.');
        }

        return redirect()->route('helper.cases')->with('success', 'Case declined and returned to the queue.');
    }
}