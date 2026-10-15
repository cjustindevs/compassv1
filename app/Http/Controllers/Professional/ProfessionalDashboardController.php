<?php

namespace App\Http\Controllers\Professional;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\ProfessionalNote;
use App\Models\Referral;
use App\Services\AnalyticsPeriod;
use App\Services\ProfessionalAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfessionalDashboardController extends Controller
{
    public function index(Request $request, ProfessionalAnalytics $reporting)
    {
        $professional = Auth::user()->psychologyProfessional;

        if (! $professional) {
            abort(403, 'No psychology professional profile found for this account.');
        }

        $professionalId = $professional->id;
        $period = AnalyticsPeriod::fromRequest($request);
        $analyticsDates = $period->dates();
        try {
            $analytics = $reporting->data($professionalId, $period);
        } catch (\Throwable $exception) {
            report($exception);
            $analytics = null;
        }

        // Stats
        $pendingReferrals = $reporting->base($professionalId)->whereHas('session', fn ($q) => $q->whereNull('archived_at'))
            ->where('status', Referral::STATUS_PENDING_PROFESSIONAL)
            ->count();

        $activeCases = $reporting->base($professionalId)->whereHas('session', fn ($q) => $q->whereNull('archived_at'))
            ->whereIn('status', Referral::ACTIVE_STATUSES)
            ->count();

        $completedCases = $analytics['completed']['total'] ?? null;
        $avgResponseHours = $analytics['response_hours'] ?? null;

        // Recent referrals
        $recentReferrals = Referral::with(['session.seeker:id,id,generated_alias', 'helper:id,id,first_name,last_name', 'adviser:id,id,first_name,last_name'])
            ->professionalAuthorized()->where('professional_id', $professionalId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Active cases (with latest professional note for "last update")
        $activeCasesList = Referral::with(['session.seeker:id,id,generated_alias', 'professionalNotes'])
            ->professionalAuthorized()->where('professional_id', $professionalId)
            ->whereHas('session', fn ($q) => $q->whereNull('archived_at'))
            ->whereIn('status', Referral::ACTIVE_STATUSES)
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();

        // Recent activity notifications
        $notifications = Notification::where('user_account_id', Auth::id())
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $unreadNotifications = Notification::where('user_account_id', Auth::id())
            ->unread()
            ->count();

        return view('professional.dashboard', compact(
            'professional',
            'pendingReferrals',
            'activeCases',
            'completedCases',
            'avgResponseHours',
            'recentReferrals',
            'activeCasesList',
            'notifications',
            'unreadNotifications',
            'analytics',
            'analyticsDates'
        ));
    }

    public function getStats()
    {
        $professional = Auth::user()->psychologyProfessional;

        if (! $professional) {
            return response()->json(['pending' => 0, 'active' => 0, 'completed' => 0]);
        }

        $professionalId = $professional->id;

        return response()->json([
            'pending' => Referral::professionalAuthorized()->professionalAuthorized()->where('professional_id', $professionalId)
                ->whereHas('session', fn ($q) => $q->whereNull('archived_at'))
                ->where('status', Referral::STATUS_PENDING_PROFESSIONAL)->count(),
            'active' => Referral::professionalAuthorized()->professionalAuthorized()->where('professional_id', $professionalId)
                ->whereHas('session', fn ($q) => $q->whereNull('archived_at'))
                ->whereIn('status', Referral::ACTIVE_STATUSES)->count(),
            'completed' => Referral::professionalAuthorized()->professionalAuthorized()->where('professional_id', $professionalId)
                ->whereIn('status', Referral::COMPLETED_STATUSES)->count(),
            'awaiting_review' => Referral::professionalAuthorized()->where('professional_id', $professionalId)
                ->whereHas('session', fn ($q) => $q->whereNull('archived_at'))->whereIn('status', Referral::ACTIVE_STATUSES)->get()
                ->filter(fn ($referral) => $referral->hasPendingCompletion())->count(),
        ]);
    }

}
