<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Services\AdviserTranscriptAccess;
use App\Services\ChatTranscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdviserTranscriptController extends Controller
{
    public function __construct(protected ChatTranscriptionService $transcriptionService) {}

    public function index(): View
    {
        $unverifiedTranscripts = $this->transcriptionService->getUnverifiedTranscripts(Auth::user()->adviser?->id ?? 0);

        return view('adviser.transcript-review', compact('unverifiedTranscripts'));
    }

    public function access(Request $request, int $sessionId): View
    {
        // Preserve a rejected form only on its own session row; the route remains authoritative.
        $request->merge(['access_session_id' => $sessionId]);
        if (is_string($request->input('reason'))) {
            $request->merge(['reason' => trim($request->input('reason'))]);
        }
        $data = $request->validate([
            'purpose' => ['required', 'string', \Illuminate\Validation\Rule::in(AdviserTranscriptAccess::PURPOSES)],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'purpose.required' => 'Select a review purpose.',
            'purpose.in' => 'Select one of the listed review purposes.',
            'reason.min' => 'Explain your reason for access in at least 10 characters.',
            'reason.max' => 'Keep your access reason within 500 characters.',
        ]);
        $session = Session::findOrFail($sessionId);
        app(AdviserTranscriptAccess::class)->grant($session, $data['purpose'], $data['reason']);
        $transcript = $this->transcriptionService->getTranscriptForUser($session, Auth::id(), 'adviser');
        abort_unless($transcript, 403);

        return view('adviser.transcript-content', compact('transcript', 'session'));
    }

    public function verify(int $sessionId): RedirectResponse
    {
        if (! $this->transcriptionService->verifyTranscript($sessionId, Auth::user()->adviser?->id ?? 0)) {
            abort(403, 'You are not authorized to verify this transcript.');
        }

        return redirect()->route('adviser.transcripts')->with('success', 'Transcript verified successfully.');
    }
}
