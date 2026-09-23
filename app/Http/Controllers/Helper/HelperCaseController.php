<?php

namespace App\Http\Controllers\Helper;

use App\Events\CaseDeclined;
use App\Http\Controllers\Controller;
use App\Models\Helper;
use App\Models\Notification;
use App\Models\Session;
use App\Services\HelperMatchingService;
use App\Services\HelperWorkflowMaintenance;
use App\Services\SeekerWorkflowService;
use App\Services\SupportAudit;
use App\Traits\BroadcastsSafely;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HelperCaseController extends Controller
{
    use BroadcastsSafely;

    /**
     * List all sessions assigned to the logged-in helper (from the database).
     */
    public function index()
    {
        abort_unless(auth()->user()?->role === 'helper' && auth()->user()?->is_active, 403);
        $helper = Auth::user()->helper;

        $sessions = Session::with(['seeker:id,id,user_account_id,generated_alias,age,gender', 'seeker.user:id,id,preferred_language', 'concern:id,concern_name', 'evaluation'])
            ->where('helper_id', $helper->id)
            ->orderByDesc('created_date')
            ->limit(50)
            ->get();

        $cases = $sessions->map(function (Session $session) {
            $expired = $session->session_status === 'helper_assigned' && $session->pre_session_brief_expires_at?->isPast();

            return [
                'id' => $session->id,
                'reference' => $session->reference_number,
                'alias' => $session->seeker->generated_alias ?? 'Unknown',
                'status' => $session->session_status,
                'status_label' => $expired ? 'Recommendation expired' : ($session->session_status === 'helper_assigned' ? ($session->helper_accepted_at ? 'Ready to start' : 'Awaiting acceptance') : $session->status_label),
                'accepted' => (bool) $session->helper_accepted_at,
                'risk' => 'Assigned support',
                'risk_class' => 'assigned',
                'mode' => $session->mode_label,
                'language' => $session->seeker?->user?->preferred_language ?: 'English',
                'concern' => $session->concern->concern_name ?? 'No concern specified',
                'age' => $session->seeker?->age,
                'gender' => $session->seeker?->gender,
                'created' => $session->created_at?->format('M d, Y h:i A'),
                'waiting' => $session->created_at?->diffForHumans(),
                'scheduled_start' => $session->scheduled_start,
                'rating' => $session->evaluation ? (int) round((float) $session->evaluation->overall_score) : null,
                'active' => $session->session_status === 'active',
                'expired' => $expired,
                'pending' => $session->session_status === 'helper_assigned' && ! $expired,
                'completed' => in_array($session->session_status, ['completed', 'evaluated', 'cancelled', 'no_show']),
            ];
        });

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
        abort_unless(auth()->user()?->role === 'helper' && auth()->user()?->is_active, 403);
        $helper = Auth::user()->helper;

        $session = Session::with([
            'seeker',
            'seeker.user',
            'concern',

            'report',
            'evaluation',
            'callLog',
        ])
            ->where('helper_id', $helper->id)
            ->findOrFail($id);

        $session->setRelation('messages', $session->helper_accepted_at && $session->isActive() ? $session->messages()->orderBy('sent_datetime')->limit(50)->get() : collect());

        return view('helper.cases-show', compact('session'));
    }

    /**
     * Accept a pending helper_assigned case — mark it active.
     */
    public function accept(Request $request, int $id)
    {
        abort_unless(auth()->user()?->role === 'helper' && auth()->user()?->is_active, 403);
        $helper = Auth::user()->helper;

        $session = Session::with(['seeker', 'helper'])
            ->where('helper_id', $helper->id)
            ->findOrFail($id);

        try {
            $session = app(SeekerWorkflowService::class)->accept(Auth::user(), $session);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            if ($e->getStatusCode() === 409 && $session->pre_session_brief_expires_at?->isPast()) {
                app(HelperWorkflowMaintenance::class)->releaseRecommendation($session, 'expired');
            }

            return redirect()->route('helper.cases')->with('error', $e->getMessage());
        }

        return redirect()->route('helper.session.pre-assessment', $session->id)->with('success', 'Request accepted. Review the brief, then start the session.');
    }

    /**
     * Decline a pending case — release it back to the queue and try to
     * match another available helper right away.
     */
    public function decline(Request $request, int $id)
    {
        abort_unless(auth()->user()?->role === 'helper' && auth()->user()?->is_active, 403);
        $helper = Auth::user()->helper;
        abort_unless(Auth::user()->role === 'helper' && Auth::user()->is_active && $helper, 403);
        $data = $request->validate(['reason' => 'required|in:fatigue,illness,personal_emergency,academic_conflict,conflict_of_interest,unavailable']);
        try {
            $session = DB::transaction(function () use ($helper, $id, $data) {
                Helper::whereKey($helper->id)->lockForUpdate()->firstOrFail();
                $session = Session::where('helper_id', $helper->id)->lockForUpdate()->findOrFail($id);
                abort_unless($session->session_status === 'helper_assigned', 409, 'This case can no longer be declined.');
                abort_if($session->pre_session_brief_expires_at?->isPast(), 409, 'This recommendation expired. Await a new match.');
                $session->update(['helper_id' => null, 'session_status' => 'waiting', 'match_status' => 'declined', 'helper_accepted_at' => null, 'scheduled_start' => null, 'pre_session_brief_expires_at' => null]);
                $session->queue?->update(['request_status' => 'waiting', 'assigned_helper_id' => null, 'matched_date' => null, 'helper_declined_at' => now()]);
                $helper->syncSessionCounters();
                if (! $helper->activeSessions()->exists()) {
                    $helper->update(['status' => 'available']);
                }
                SupportAudit::record('helper_declined', $session, ['reason' => $data['reason']]);
                if ($data['reason'] === 'conflict_of_interest') {
                    DB::table('helper_conflicts')->updateOrInsert(['helper_id' => $helper->id, 'seeker_id' => $session->seeker_id], ['reported_by' => Auth::id(), 'created_at' => now()]);
                }
                if ($session->seeker) {
                    Notification::create(['user_account_id' => $session->seeker->user_account_id,
                        'title' => 'Helper Unavailable', 'message' => 'Your helper declined. Your request is waiting for another eligible helper.',
                        'notification_type' => 'session', 'type_icon' => 'fa-triangle-exclamation', 'link' => '/request/matching']);
                }

                return $session;
            }, 3);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $current = Session::find($id);
            if ($current?->pre_session_brief_expires_at?->isPast()) {
                app(HelperWorkflowMaintenance::class)->releaseRecommendation($current, 'expired');

                return redirect()->route('helper.cases')->with('error', 'This recommendation expired. Await a new match.');
            }

            return redirect()->route('helper.cases')->with('error', $e->getMessage());
        }
        if ($session->seeker?->user_account_id) {
            $this->broadcastSafely(new CaseDeclined($session, $session->seeker->user_account_id));
        }
        if ($session->queue) {
            app(HelperMatchingService::class)->processQueueRequest($session->queue->fresh(), $helper->id);
        }

        return redirect()->route('helper.cases')->with('success', 'Case declined and returned to the queue.');
    }
}
