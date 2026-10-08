<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\Helper;
use App\Models\HelperCompetencyHistory;
use App\Models\Referral;
use App\Models\SessionReport;
use App\Models\IncidentReport;
use Illuminate\Support\Facades\Auth;

class AdviserDashboardController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $user = Auth::user();
        $adviser = app(\App\Services\AdviserScope::class)->actor($user);
        $helperIds = Helper::where('adviser_id', $adviser->id)->pluck('id');
        $pendingEvaluations = SessionReport::pendingEvaluationForAdviser((int) $adviser->id)
            ->with(['session.seeker', 'session.helper', 'session.concern'])
            ->oldest()->limit(12)->get();
        $helpers = Helper::whereIn('id', $helperIds)->with(['user', 'currentReadiness', 'schedule'])
            ->orderBy('first_name')->limit(20)->get();
        return view('dashboard.adviser', [
            'overview' => app(\App\Services\DashboardOverview::class)->forUser($user),
            'pendingEvaluations' => $pendingEvaluations,
            'helpers' => $helpers,
            'totalHelpers' => $helperIds->count(),
            'recentActivity' => $this->getRecentActivity($helperIds),
        ]);
    }

    private function getRecentActivity($helperIds): array
    {
        $activity = [];

        // Recent emergency incidents
        foreach (IncidentReport::with('session.seeker:id,id,generated_alias')
            ->where('risk_level', 'emergency')
            ->whereHas('session', fn ($query) => $query->whereIn('helper_id', $helperIds))
            ->latest()
            ->limit(2)
            ->get() as $incident) {
            $activity[] = [
                'type' => 'emergency',
                'message' => 'Emergency case flagged',
                'detail' => $incident->session?->seeker?->generated_alias ?? 'A seeker' . ' - ' . \Illuminate\Support\Str::limit($incident->description, 60),
                'time' => $incident->created_at?->diffForHumans(), 'timestamp' => $incident->created_at?->timestamp ?? 0,
            ];
        }

        // Recent competency evaluations
        foreach (HelperCompetencyHistory::with('helper:id,id,first_name,last_name')
            ->whereIn('helper_id', $helperIds)
            ->latest()
            ->limit(2)
            ->get() as $evaluation) {
            $activity[] = [
                'type' => 'evaluation',
                'message' => 'New evaluation submitted',
                'detail' => ($evaluation->helper?->full_name ?? 'Helper') . ' - ' . ($evaluation->overall_score ? 'Score ' . $evaluation->overall_score . '/5' : 'Reviewed'),
                'time' => $evaluation->created_at?->diffForHumans(), 'timestamp' => $evaluation->created_at?->timestamp ?? 0,
            ];
        }

        // Recent referrals
        foreach (Referral::with(['session.seeker:id,id,generated_alias'])
            ->whereIn('helper_id', $helperIds)
            ->latest()
            ->limit(2)
            ->get() as $referral) {
            $activity[] = [
                'type' => 'referral',
                'message' => 'Referral ' . ucfirst(str_replace('_', ' ', $referral->status)),
                'detail' => ($referral->session?->seeker?->generated_alias ?? 'A seeker') . ' - ' . \Illuminate\Support\Str::limit($referral->referral_reason, 60),
                'time' => $referral->created_at?->diffForHumans(), 'timestamp' => $referral->created_at?->timestamp ?? 0,
            ];
        }

        // Availability history records the change itself, rather than any profile edit.
        foreach (\App\Models\HelperAvailabilityLog::whereIn('helper_id', $helperIds)->with('helper')->latest('changed_at')->limit(2)->get() as $log) {
            $activity[] = [
                'type' => 'helper', 'message' => 'Helper availability updated',
                'detail' => ($log->helper?->full_name ?: 'Helper').' - '.ucwords(str_replace('_',' ', $log->new_status)),
                'time' => $log->changed_at?->diffForHumans(), 'timestamp' => $log->changed_at?->timestamp ?? 0,
            ];
        }

        // Most recent first
        usort($activity, fn ($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return array_slice($activity, 0, 8);
    }
}
