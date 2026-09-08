<?php

namespace App\Http\ViewComposers;

use App\Models\Helper;
use App\Models\HelperCompetencyHistory;
use App\Models\IncidentReport;
use App\Models\QueueRequest;
use App\Models\Referral;
use App\Models\Session;
use App\Models\SessionReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SidebarComposer
{
    public function compose($view): void
    {
        $user = Auth::user();

        if (!$user) {
            return;
        }

        $role = $user->role;

        $data = match ($role) {
            'helper'             => $this->helperData($user),
            'adviser'            => $this->adviserData($user),
            'moderator'          => $this->moderatorData($user),
            'professional'       => $this->professionalData($user),
            default              => $this->seekerData($user),
        };

        $view->with($data);
    }

    private function helperData($user): array
    {
        $userId = $user->id;
        $cacheKey = "sidebar_helper_{$userId}";

        return Cache::remember($cacheKey, 60, function () use ($user) {
            $helper = $user->helper;

            if (!$helper) {
                return [
                    'helper'             => null,
                    'helperName'         => $user->name ?? 'Helper',
                    'totalSessions'      => 0,
                    'competencyScore'    => 0,
                    'availabilityStatus' => 'offline',
                    'availabilityLabel'  => 'Offline',
                    'caseBadgeCount'     => 0,
                    'notifBadgeCount'    => 0,
                    'activeSession'      => null,
                    'voiceUrl'           => route('helper.cases'),
                    'notesUrl'           => route('helper.cases'),
                    'initials'           => strtoupper(substr($user->name ?? 'H', 0, 2)),
                ];
            }

            $totalSessions = Session::where('helper_id', $helper->id)->count();

            $competencyHistory = HelperCompetencyHistory::where('helper_id', $helper->id)
                ->latest('evaluation_date')
                ->first();

            $competencyScore = (int) round((float) ($competencyHistory?->overall_score ?? 0));

            $availabilityStatus = $helper->latestReadiness?->availability_status ?? $helper->status ?? 'offline';
            $availabilityLabel = ucfirst((string) $availabilityStatus);

            $caseBadgeCount = Session::where('helper_id', $helper->id)
                ->whereIn('session_status', ['helper_assigned', 'active'])
                ->count();

            $notifBadgeCount = $user->unreadNotifications()->count();

            $activeSession = Session::where('helper_id', $helper->id)
                ->whereIn('session_status', ['active', 'helper_assigned'])
                ->latest('created_date')
                ->first();

            $voiceUrl = $activeSession ? route('helper.session.voice', ['id' => $activeSession->id]) : route('helper.cases');
            $notesUrl = $activeSession ? route('helper.session.notes', ['id' => $activeSession->id]) : route('helper.cases');

            $helperName = $helper->full_name ?: $user->name ?: 'Helper';
            $initials = Str::substr($helperName, 0, 2);

            return compact(
                'helper', 'helperName', 'totalSessions', 'competencyScore',
                'availabilityStatus', 'availabilityLabel', 'caseBadgeCount',
                'notifBadgeCount', 'activeSession', 'voiceUrl', 'notesUrl', 'initials'
            );
        });
    }

    private function adviserData($user): array
    {
        $userId = $user->id;
        $cacheKey = "sidebar_adviser_{$userId}";

        return Cache::remember($cacheKey, 60, function () use ($user) {
            $adviserProfile = $user->adviser;
            $adviserHelperIds = Helper::where('adviser_id', $adviserProfile?->id)->pluck('id');

            $evalBadge = SessionReport::where('adviser_reviewed', false)
                ->whereHas('session', fn ($q) => $q->whereIn('helper_id', $adviserHelperIds))
                ->count();

            $referralBadge = Referral::where('status', 'pending_adviser')
                ->whereIn('helper_id', $adviserHelperIds)
                ->count();

            $notifBadge = $user->unreadNotifications()->count();

            $totalHelpers = $adviserHelperIds->count();

            $activeSessions = Session::whereIn('helper_id', $adviserHelperIds)
                ->where('session_status', 'active')
                ->count();

            $pendingReviews = $evalBadge;

            $avatarText = $adviserProfile?->first_name
                ? substr($adviserProfile->first_name, 0, 1) . substr($adviserProfile->last_name, 0, 1)
                : strtoupper(substr($user->name, 0, 2));

            $displayName = $adviserProfile?->full_name ?? $user->name;

            return compact(
                'evalBadge', 'referralBadge', 'notifBadge', 'totalHelpers',
                'activeSessions', 'pendingReviews', 'avatarText', 'displayName'
            );
        });
    }

    private function moderatorData($user): array
    {
        $userId = $user->id;
        $cacheKey = "sidebar_moderator_{$userId}";

        return Cache::remember($cacheKey, 60, function () use ($user) {
            $queueCount = QueueRequest::where('request_status', 'waiting')->count();
            $sessionCount = Session::where('session_status', 'active')->count();
            $emergencyCount = IncidentReport::whereIn('status', ['open', 'under_review', 'escalated'])->count();
            $notifBadge = $user->unreadNotifications()->count();

            $moderatorProfile = $user->moderator;
            $avatarText = $moderatorProfile?->first_name
                ? substr($moderatorProfile->first_name, 0, 1) . substr($moderatorProfile->last_name, 0, 1)
                : strtoupper(substr($user->name, 0, 2));

            $displayName = $moderatorProfile?->full_name ?? $user->name;

            return compact(
                'queueCount', 'sessionCount', 'emergencyCount', 'notifBadge',
                'avatarText', 'displayName'
            );
        });
    }

    private function professionalData($user): array
    {
        $userId = $user->id;
        $cacheKey = "sidebar_professional_{$userId}";

        return Cache::remember($cacheKey, 60, function () use ($user) {
            $professionalProfile = $user->psychologyProfessional;
            $professionalId = $professionalProfile?->id ?? 0;

            $pending = Referral::where('professional_id', $professionalId)
                ->where('status', Referral::STATUS_PENDING_PROFESSIONAL)
                ->count();

            $active = Referral::where('professional_id', $professionalId)
                ->whereIn('status', Referral::ACTIVE_STATUSES)
                ->count();

            $completed = Referral::where('professional_id', $professionalId)
                ->whereIn('status', Referral::COMPLETED_STATUSES)
                ->count();

            $notifBadge = $user->unreadNotifications()->count();

            $displayName = $professionalProfile?->full_name ?? $user->name;
            $avatarText = $professionalProfile?->initials ?? strtoupper(substr($user->name ?? 'PR', 0, 2));
            $isAvailable = $professionalProfile?->is_available;

            return compact(
                'pending', 'active', 'completed', 'notifBadge',
                'displayName', 'avatarText', 'isAvailable'
            );
        });
    }

    private function seekerData($user): array
    {
        $userId = $user->id;
        $cacheKey = "sidebar_seeker_{$userId}";

        return Cache::remember($cacheKey, 60, function () use ($user) {
            $badgeNotifications = $user->unreadNotifications()->count();

            return compact('badgeNotifications');
        });
    }
}
