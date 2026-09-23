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
        $data = $request->validate(['purpose' => 'required|string', 'reason' => 'required|string|min:10|max:500']);
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

        return back()->with('success', 'Transcript verified successfully.');
    }
}
