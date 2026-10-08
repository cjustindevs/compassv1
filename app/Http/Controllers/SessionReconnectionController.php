<?php

namespace App\Http\Controllers;

use App\Models\Session;
use App\Models\SessionReconnection;
use App\Services\SessionReconnectionService;
use Illuminate\Http\Request;

class SessionReconnectionController extends Controller
{
    public function state(Request $request, Session $session, SessionReconnectionService $service)
    {
        abort_unless($request->user()->is_active && (($request->user()->role === 'seeker' && $request->user()->helpSeeker?->id === $session->seeker_id) || ($request->user()->role === 'helper' && $request->user()->helper?->id === $session->helper_id)), 403);
        $incident = $service->current($session);

        return response()->json(['status' => $incident?->status, 'active' => $session->isActive(), 'can_choose' => $session->isActive() && $incident && in_array($incident->status, ['interrupted', 'waiting', 'requested', 'offered']) && $incident->detected_at->lte(now()->subMinutes(2)), 'transferred' => (bool) $incident?->continuation_id]);
    }

    public function heartbeat(Session $session, SessionReconnectionService $service)
    {
        $service->heartbeat($session);

        return response()->noContent();
    }

    public function choose(Request $request, Session $session, SessionReconnectionService $service)
    {
        $data = $request->validate(['decision' => 'required|in:wait,replace']);
        $service->choose($session, $data['decision']);

        return response()->json(['success' => true]);
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->is_active && in_array($request->user()->role, ['helper', 'moderator']), 403);
        abort_unless($request->user()->role === ($request->routeIs('helper.*') ? 'helper' : 'moderator'), 403);
        if ($request->user()->role === 'helper') {
            abort_unless($request->user()->helper, 403);
        }$incidents = SessionReconnection::with('session')->whereHas('session', fn ($q) => $q->where('session_status', 'active'))->whereIn('status', ['interrupted', 'waiting', 'requested', 'offered'])->when($request->user()->role === 'helper', fn ($q) => $q->where('offered_helper_id', $request->user()->helper?->id)->where('status', 'offered'))->latest()->paginate(15);

        return view('session.reconnections', compact('incidents'));
    }

    public function offer(SessionReconnection $incident, SessionReconnectionService $service)
    {
        $service->offer($incident);

        return back()->with('success', 'Offer sent to an eligible Helper.');
    }

    public function accept(Request $request, SessionReconnection $incident, SessionReconnectionService $service)
    {
        $data = $request->validate(['accept' => 'required|boolean']);
        $service->accept($incident, (bool) $data['accept']);

        return $data['accept'] ? redirect('/helper/chat')->with('success','Replacement accepted. The original session deadline still applies.') : back()->with('success','Offer declined. Moderator notified.');
    }
}
