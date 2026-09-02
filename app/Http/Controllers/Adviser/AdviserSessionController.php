<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdviserSessionController extends Controller
{
    /**
     * Live, read-only monitoring view for a session an adviser supervises.
     * The page subscribes to the private `session.{id}` Echo channel so new
     * messages, status changes, and session-end events appear in real time.
     */
    public function show($id)
    {
        $adviser = Auth::user()->adviser;

        $session = Session::with([
            'seeker',
            'helper',
            'concern',
            'messages' => fn ($query) => $query->orderBy('sent_datetime', 'asc'),
            'sessionReport',
        ])->findOrFail($id);

        // Authorization: only advisers who supervise this session may view it.
        $isHelperAdviser = $session->helper_id
            && $session->helper?->adviser_id === $adviser?->id;

        $isReferralAdviser = Referral::where('session_id', $session->id)
            ->where('adviser_id', $adviser?->id)
            ->exists();

        if (! ($isHelperAdviser || $isReferralAdviser)) {
            abort(403, 'You do not have access to this session.');
        }

        return view('adviser.session', compact('session'));
    }
}
