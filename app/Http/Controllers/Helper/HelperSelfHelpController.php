<?php

namespace App\Http\Controllers\Helper;

use App\Http\Controllers\Controller;
use App\Models\EmergencyResource;
use App\Models\HelperJournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HelperSelfHelpController extends Controller
{
    /**
     * Self-help landing shown to helpers who are not ready to take sessions.
     */
    public function index()
    {
        return view('helper.self-help.index');
    }

    /**
     * Guided breathing exercise.
     */
    public function breathing()
    {
        return view('helper.self-help.breathing');
    }

    /**
     * Guided grounding exercise.
     */
    public function grounding()
    {
        return view('helper.self-help.grounding');
    }

    /**
     * Journal list + create form.
     */
    public function journal()
    {
        $helper = Auth::user()->helper;
        $entries = HelperJournalEntry::where('helper_id', $helper->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('helper.self-help.journal', compact('entries'));
    }

    /**
     * Save a journal entry.
     */
    public function storeJournal(Request $request)
    {
        $validated = $request->validate([
            'content' => 'required|string|min:3|max:2000',
            'mood' => 'nullable|in:good,okay,neutral,anxious,sad,tired',
        ]);

        $helper = Auth::user()->helper;

        HelperJournalEntry::create([
            'helper_id' => $helper->id,
            'content' => $validated['content'],
            'mood' => $validated['mood'] ?? 'neutral',
        ]);

        return back()->with('success', 'Journal entry saved.');
    }

    /**
     * Emergency / hotline resources.
     */
    public function hotlines()
    {
        $hotlines = EmergencyResource::where('status', 'active')->get();

        return view('helper.self-help.hotlines', compact('hotlines'));
    }
}
