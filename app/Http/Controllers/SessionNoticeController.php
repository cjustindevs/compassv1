<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Session;
use App\Services\SessionDurationService;
use App\Services\SessionReconnectionService;
use App\Services\SupportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Claims visible session notices once per participant across tabs and devices. */
class SessionNoticeController extends Controller
{
    public function claim(Request $request, Session $session)
    {
        Gate::authorize('participate', $session);
        abort_unless($request->user()->is_active,403);
        $data = $request->validate(['event' => 'required|in:ending_soon,helper_disconnected']);
        $show = DB::transaction(function () use ($request, $session, $data) {
            $case = Session::lockForUpdate()->findOrFail($session->id);
            Gate::authorize('participate', $case);
            if (!$case->isActive() || !$case->start_time) return false;
            $suffix = '';
            if ($data['event'] === 'ending_soon') {
                $remaining = app(SessionDurationService::class)->state($case)['remaining_seconds'];
                if ($remaining === null || $remaining <= 0 || $remaining > 300) return false;
                \App\Models\Notification::withoutGlobalScopes()->firstOrCreate([
                    'user_account_id'=>$request->user()->id, 'popup_event_key'=>'session-ending:'.$case->id,
                ], ['title'=>'Five minutes remaining','message'=>'Please begin wrapping up. This session closes at the 90-minute limit.',
                    'notification_type'=>'session','link'=>$request->user()->role==='helper' ? '/helper/cases/'.$case->id : '/seeker/requests/'.$case->id]);
            } else {
                if ($request->user()->role !== 'seeker') return false;
                $incident = app(SessionReconnectionService::class)->current($case);
                if (!$incident || !in_array($incident->status, ['interrupted','waiting','requested','offered'], true)
                    || app(SessionReconnectionService::class)->absentMinutes($case, $incident) < 10) return false;
                $suffix = ':'.$incident->id;
            }
            $action = 'session_notice_'.$data['event'].$suffix;
            if (AuditLog::where('target_type', $case->getTable())->where('target_id', $case->id)
                ->where('user_account_id', $request->user()->id)->where('action', $action)->exists()) return false;
            SupportAudit::record($action, $case);
            return true;
        });
        return response()->json(['show' => $show])->header('Cache-Control', 'no-store, private');
    }
}
