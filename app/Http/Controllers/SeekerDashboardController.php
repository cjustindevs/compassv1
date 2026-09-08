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
        $helpSeeker = Auth::user()->helpSeeker;

        $pendingSession = null;
        $activeSession = null;
        $totalSessions = 0;
        $completedSessions = 0;
        $totalEvaluations = 0;
        $recentSessions = collect();

        if ($helpSeeker) {
            Session::where('seeker_id', $helpSeeker->id)->abandoned()->markAbandoned();
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
                ->count();

            $totalEvaluations = HelpSeekerEvaluation::whereHas('session', function ($query) use ($helpSeeker) {
                $query->where('seeker_id', $helpSeeker->id);
            })->count();

            $recentSessions = Session::with(['helper:id,id,first_name,last_name', 'concern:id,concern_name', 'evaluation'])
                ->where('seeker_id', $helpSeeker->id)
                ->latest('created_date')
                ->limit(5)
                ->get();
        }

        return view('dashboard.seeker', compact(
            'pendingSession',
            'activeSession',
            'totalSessions',
            'completedSessions',
            'totalEvaluations',
            'recentSessions'
        ));
    }
}
