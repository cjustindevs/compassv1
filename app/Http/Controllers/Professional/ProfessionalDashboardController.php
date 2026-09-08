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
        $recentReferrals = Referral::with(['session.seeker:id,id,generated_alias', 'helper:id,id,first_name,last_name', 'adviser:id,id,first_name,last_name'])
            ->where('professional_id', $professionalId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Active cases (with latest professional note for "last update")
        $activeCasesList = Referral::with(['session.seeker:id,id,generated_alias', 'professionalNotes'])
            ->where('professional_id', $professionalId)
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
     * change, computed in SQL rather than in PHP for performance.
     */
    private function averageResponseHours(int $professionalId): int
    {
        $avg = Referral::where('professional_id', $professionalId)
            ->whereIn('status', [...Referral::ACTIVE_STATUSES, ...Referral::COMPLETED_STATUSES])
            ->whereNotNull('created_at')
            ->whereNotNull('updated_at')
            ->selectRaw('AVG(' . \App\Support\DatabaseHelper::secondsBetween('updated_at', 'created_at') . ') / 3600 as avg_hours')
            ->first();

        return (int) round((float) ($avg->avg_hours ?? 0));
    }
}
