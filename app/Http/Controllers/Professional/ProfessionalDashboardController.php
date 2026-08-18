<?php

namespace App\Http\Controllers\Professional;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\ProfessionalNote;
use App\Models\Referral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfessionalDashboardController extends Controller
{
    public function index()
    {
        $professional = Auth::user()->psychologyProfessional;

        if (! $professional) {
            abort(403, 'No psychology professional profile found for this account.');
        }

        $professionalId = $professional->id;

        // Stats
        $pendingReferrals = Referral::where('professional_id', $professionalId)
            ->where('status', Referral::STATUS_PENDING_PROFESSIONAL)
            ->count();

        $activeCases = Referral::where('professional_id', $professionalId)
            ->whereIn('status', Referral::ACTIVE_STATUSES)
            ->count();

        $completedCases = Referral::where('professional_id', $professionalId)
            ->whereIn('status', Referral::COMPLETED_STATUSES)
            ->count();

        $avgResponseHours = $this->averageResponseHours($professionalId);

        // Recent referrals
        $recentReferrals = Referral::with(['session', 'session.seeker', 'helper', 'adviser'])
            ->where('professional_id', $professionalId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Active cases (with latest professional note for "last update")
        $activeCasesList = Referral::with(['session', 'session.seeker', 'professionalNotes'])
            ->where('professional_id', $professionalId)
            ->whereIn('status', Referral::ACTIVE_STATUSES)
            ->orderByDesc('updated_at')
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
            'unreadNotifications'
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
            'pending' => Referral::where('professional_id', $professionalId)
                ->where('status', Referral::STATUS_PENDING_PROFESSIONAL)->count(),
            'active' => Referral::where('professional_id', $professionalId)
                ->whereIn('status', Referral::ACTIVE_STATUSES)->count(),
            'completed' => Referral::where('professional_id', $professionalId)
                ->whereIn('status', Referral::COMPLETED_STATUSES)->count(),
        ]);
    }

    /**
     * Average time (hours) between referral assignment and the first status
     * change, computed from real created_at / updated_at timestamps.
     */
    private function averageResponseHours(int $professionalId): int
    {
        $responded = Referral::where('professional_id', $professionalId)
            ->whereIn('status', [...Referral::ACTIVE_STATUSES, ...Referral::COMPLETED_STATUSES])
            ->get()
            ->filter(fn (Referral $referral) => $referral->created_at && $referral->updated_at)
            ->filter(fn (Referral $referral) => $referral->updated_at->greaterThan($referral->created_at))
            ->map(fn (Referral $referral) => $referral->created_at->diffInHours($referral->updated_at));

        if ($responded->isEmpty()) {
            return 0;
        }

        return (int) round($responded->avg());
    }
}
