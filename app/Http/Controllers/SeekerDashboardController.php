<?php

namespace App\Http\Controllers;

use App\Models\HelpSeekerEvaluation;
use App\Models\Session;
use Illuminate\Support\Facades\Auth;

class SeekerDashboardController extends Controller
{
    /**
     * Show the seeker dashboard with resume-aware request state:
     * a pending in-progress request and/or an active session, plus
     * real stats from the database.
     */
    public function index()
    {
        \Illuminate\Support\Facades\Gate::authorize('seeker-workflow');
        $helpSeeker = Auth::user()->helpSeeker;
        $period = \App\Services\AnalyticsPeriod::fromRequest(request());
        $analyticsDates = $period->dates();

        $pendingSession = null;
        $activeSession = null;
        $totalSessions = 0;
        $completedSessions = 0;
        $totalEvaluations = 0;
        $recentSessions = collect();
        $seekerIndicators = [];

        if ($helpSeeker) {

            // Kill stale requests (>24h, never accepted) so the dashboard
            // never shows a phantom "pending request".
            $pendingSession = Session::pendingForSeeker($helpSeeker->id)->first();

            $activeSession = Session::where('seeker_id', $helpSeeker->id)
                ->where('session_status', Session::STATUS_ACTIVE)
                ->orderByDesc('start_time')
                ->first();

            $totalSessions = Session::where('seeker_id', $helpSeeker->id)->count();

            $completedSessions = Session::where('seeker_id', $helpSeeker->id)
                ->whereIn('session_status', [Session::STATUS_COMPLETED, Session::STATUS_EVALUATED])
                ->where('end_time', '>=', $period->start->utc())->where('end_time', '<', $period->end->utc())
                ->count();

            $totalEvaluations = HelpSeekerEvaluation::whereHas('session', function ($query) use ($helpSeeker) {
                $query->where('seeker_id', $helpSeeker->id);
            })->where('created_at', '>=', $period->start->utc())->where('created_at', '<', $period->end->utc())->count();

            $recentSessions = Session::with(['helper:id,id,first_name,last_name', 'concern:id,concern_name', 'evaluation'])
                ->where('seeker_id', $helpSeeker->id)
                ->where('created_date', '>=', $period->start->utc())->where('created_date', '<', $period->end->utc())
                ->latest('created_date')
                ->limit(5)
                ->get();

            $current = $activeSession ?? $pendingSession;
            $latestReferral = \App\Models\Referral::whereHas('session', fn ($q) => $q->where('seeker_id', $helpSeeker->id))->whereNotNull('approved_at')->latest('id')->first();
            $feedbackDue = Session::where('seeker_id', $helpSeeker->id)->whereIn('session_status', ['completed', 'evaluated'])->whereDoesntHave('evaluation')->count();
            $seekerIndicators = [
                ['label'=>'Waiting status', 'value'=>$activeSession ? 'Connected' : ($pendingSession ? ($pendingSession->helper_id ? 'Helper assigned' : 'Request in progress') : 'No waiting request'), 'detail'=>'Live status; priority matching may change the order', 'link'=>route('request.matching'), 'action'=>'View request'],
                ['label'=>'Session status', 'value'=>$current ? ucwords(str_replace('_',' ',$current->session_status)) : 'No current session', 'detail'=>$current?->helper?->public_alias ? 'Helper: '.$current->helper->public_alias : 'Your current support request', 'link'=>$activeSession ? route($activeSession->session_type === 'voice' ? 'session.voice' : 'session.chat') : route('request.screening'), 'action'=>$activeSession ? 'Open session' : 'Request support'],
                ['label'=>'Referral status', 'value'=>$latestReferral ? ucwords(str_replace('_',' ',$latestReferral->status)) : 'No approved referral', 'detail'=>'Your latest approved referral', 'link'=>route('seeker.referrals'), 'action'=>'View referral decisions'],
                ['label'=>'Feedback status', 'value'=>$feedbackDue ? $feedbackDue.' awaiting feedback' : 'Up to date', 'detail'=>'Your completed sessions only', 'link'=>route('session.history'), 'action'=>'View session history'],
            ];
        }

        return view('dashboard.seeker', compact(
            'analyticsDates',
            'seekerIndicators',
            'pendingSession',
            'activeSession',
            'totalSessions',
            'completedSessions',
            'totalEvaluations',
            'recentSessions'
        ));
    }
}
