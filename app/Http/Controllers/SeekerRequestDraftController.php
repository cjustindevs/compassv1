<?php

namespace App\Http\Controllers;

use App\Models\{ConcernCategory, HelpSeeker, SeekerRequestDraft, User};
use App\Services\{CompactScreening, ConsentService, SeekerWorkflowService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\Rule;

class SeekerRequestDraftController extends Controller
{
    public static function load(User $user, string $stage, ?int $sessionId = null): ?SeekerRequestDraft
    {
        // No draft is disclosed without current consent, ownership and stage.
        Gate::authorize('seeker-workflow');
        if (!app(ConsentService::class)->isFull($user->helpSeeker)) return null;
        $draft = SeekerRequestDraft::where('seeker_id', $user->helpSeeker->id)->first();
        if (!$draft) return null;
        if ($draft->expires_at->lte(now()) || $draft->instrument_version !== CompactScreening::FORM_VERSION) {
            $draft->delete();
            return null;
        }
        return $draft->stage === $stage && (int)$draft->session_id === (int)$sessionId ? $draft : null;
    }

    public function save(Request $request)
    {
        Gate::authorize('seeker-workflow');
        app(ConsentService::class)->requireGeneral($request->user());
        $base = $request->validate(['stage'=>'required|in:screening,preferences', 'instrument_version'=>['required', Rule::in([CompactScreening::FORM_VERSION])], 'session_id'=>'nullable|integer']);
        $rules = $base['stage'] === 'screening' ? CompactScreening::formRules(true) + [
            'concern_id'=>['nullable', Rule::exists('concern_categories','id')->where('is_active',true)],
            'description'=>'nullable|string|max:500', 'custom_concern'=>'nullable|string|max:255',
        ] : ['preferred_language'=>'nullable|in:English,Tagalog,English/Tagalog'];
        $payload = $request->validate($rules);
        $draft = DB::transaction(function () use ($request, $base, $payload) {
            HelpSeeker::whereKey($request->user()->helpSeeker->id)->lockForUpdate()->firstOrFail();
            app(ConsentService::class)->requireGeneral($request->user()->fresh());
            $current = app(SeekerWorkflowService::class)->current($request->user());
            if ($base['stage'] === 'screening') {
                abort_if($current || !empty($base['session_id']), 409, 'Screening is already recorded. Open your active request.');
            } else {
                abort_unless($current && $current->workflow_state === 'session_preferences_required'
                    && (int)$current->id === (int)($base['session_id'] ?? 0), 409, 'The request has changed. Open your active request.');
            }
            return SeekerRequestDraft::updateOrCreate(['seeker_id'=>$request->user()->helpSeeker->id], [
                'stage'=>$base['stage'], 'session_id'=>$current?->id, 'instrument_version'=>CompactScreening::FORM_VERSION,
                'payload'=>$payload, 'expires_at'=>now()->addDays(SeekerRequestDraft::RETENTION_DAYS),
            ]);
        });
        return response()->json(['message'=>'Draft saved. It is not a submitted request and is not monitored by staff.', 'expires_at'=>$draft->expires_at->toIso8601String()]);
    }

    public function discard(Request $request)
    {
        Gate::authorize('seeker-workflow');
        DB::transaction(function () use ($request) {
            HelpSeeker::whereKey($request->user()->helpSeeker->id)->lockForUpdate()->firstOrFail();
            SeekerRequestDraft::where('seeker_id', $request->user()->helpSeeker->id)->delete();
        });
        return response()->json(['message'=>'Saved draft discarded. Any recorded screening or submitted request remains unchanged.']);
    }
}
