<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Session;
use App\Models\SessionCoachNote;
use App\Services\AdviserScope;
use App\Services\SupportAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * QA: "dapat pwede makita ng adviser ang live session". The supervising Adviser can watch an
 * active chat of one of their Helpers, read-only (Seeker shown by alias only), and send private
 * coaching notes that only the Helper sees. Access is limited to active sessions of Helpers the
 * Adviser supervises, and every view and note is audited (supervision consent covers review).
 */
class AdviserLiveSessionController extends Controller
{
    public function index(Request $request): View
    {
        $adviser = $this->actor($request);
        $sessions = $this->liveQuery($adviser->id)->with(['helper', 'seeker', 'concern'])->orderBy('start_time')->get();

        return view('adviser.live-sessions', compact('sessions'));
    }

    public function show(Request $request, int $id): View|RedirectResponse
    {
        $adviser = $this->actor($request);
        $session = $this->liveQuery($adviser->id)->with(['helper', 'seeker', 'concern'])->find($id);
        if (! $session) {
            return redirect()->route('adviser.live.index')->with('status', 'That session is no longer live. Completed sessions are reviewed from Pending Evaluations.');
        }
        SupportAudit::record('adviser_live_session_viewed', $session, ['adviser_id' => $adviser->id]);

        return view('adviser.live-session', compact('session'));
    }

    /** Messages and coach notes after the given ids; also reports whether the session is still live. */
    public function feed(Request $request, int $id): JsonResponse
    {
        $adviser = $this->actor($request);
        $session = Session::with(['helper', 'seeker'])->findOrFail($id);
        abort_unless($session->helper?->adviser_id === $adviser->id, 403);
        $live = $session->isActive() && $session->session_type === 'chat';
        $after = (int) $request->query('after', 0);
        $notesAfter = (int) $request->query('notes_after', 0);
        $messages = $live ? Message::where('session_id', $session->id)->where('id', '>', $after)->orderBy('id')->limit(200)->get()
            ->map(fn (Message $m) => [
                'id' => $m->id,
                'from' => $m->is_helper ? 'helper' : 'seeker',
                // Alias only for the Seeker; the Helper is shown by name to their own Adviser.
                'name' => $m->is_helper ? ($session->helper?->full_name ?? 'Helper') : ($session->seeker?->generated_alias ?? 'Seeker'),
                'text' => $m->message_text,
                'time' => ($m->sent_datetime ?? $m->created_at)?->copy()->timezone('Asia/Manila')->format('g:i A'),
            ]) : collect();
        $notes = SessionCoachNote::with('adviser')->where('session_id', $session->id)->where('id', '>', $notesAfter)->orderBy('id')->get()->map->toFeed();

        return response()->json(['live' => $live, 'status' => $session->status_label, 'messages' => $messages, 'notes' => $notes]);
    }

    public function storeNote(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $adviser = $this->actor($request);
        $data = $request->validate(['note' => 'required|string|min:2|max:600']);
        $session = $this->liveQuery($adviser->id)->findOrFail($id);
        $note = SessionCoachNote::create(['session_id' => $session->id, 'adviser_id' => $adviser->id, 'helper_id' => $session->helper_id, 'note' => trim($data['note'])]);
        SupportAudit::record('adviser_coach_note_sent', $session, ['adviser_id' => $adviser->id, 'note_id' => $note->id]);
        if ($session->helper?->user_account_id) {
            Notification::create(['user_account_id' => $session->helper->user_account_id, 'notification_type' => 'session',
                'title' => 'Coach note from your Adviser', 'message' => 'Your Adviser sent a private note for your current session. Only you can see it.',
                'link' => route('helper.chat.show', $session->id, false)]);
        }

        return $request->expectsJson()
            ? response()->json(['note' => $note->load('adviser')->toFeed()], 201)
            : back()->with('success', 'Coach note sent to the Helper.');
    }

    private function actor(Request $request)
    {
        abort_unless($request->user()?->role === 'adviser' && $request->user()?->is_active, 403);

        return app(AdviserScope::class)->actor();
    }

    private function liveQuery(int $adviserId)
    {
        return Session::where('session_status', Session::STATUS_ACTIVE)->where('session_type', 'chat')
            ->where('is_test_record', false)
            ->whereHas('helper', fn ($q) => $q->where('adviser_id', $adviserId));
    }
}
