<?php

namespace App\Http\Controllers;

use App\Models\Session;
use Illuminate\Support\Facades\Auth;

class SeekerDashboardController extends Controller
{
    /**
     * Show the seeker dashboard with resume-aware request state:
     * a pending in-progress request and/or an active session.
     */
    public function index()
    {
        $helpSeeker = Auth::user()->helpSeeker;

        $pendingSession = null;
        $activeSession = null;

        if ($helpSeeker) {
            // Kill stale requests (>24h, never accepted) so the dashboard
            // never shows a phantom "pending request".
            Session::where('seeker_id', $helpSeeker->id)
                ->abandoned()
                ->markAbandoned();

            $pendingSession = Session::pendingForSeeker($helpSeeker->id)->first();

            $activeSession = Session::where('seeker_id', $helpSeeker->id)
                ->where('session_status', Session::STATUS_ACTIVE)
                ->orderByDesc('start_time')
                ->first();
        }

        return view('dashboard.seeker', compact('pendingSession', 'activeSession'));
    }
}