<?php
namespace App\Services;
use App\Models\{Helper, HelperCompetencyHistory};
class HelperSidebarStats
{
    public function forHelper(Helper $helper): array
    {
        $helper = $helper->fresh();
        $latest = HelperCompetencyHistory::where('helper_id', $helper->id)->latest('evaluation_date')->latest('id')->first();
        $state = app(HelperEligibilityService::class)->status($helper);
        // Current workload and explicit availability take precedence over eligibility reasons.
        if ($helper->sessions()->where('session_status', 'active')->exists()) {
            $state['label'] = 'In session';
            $state['assignable'] = false;
        } elseif ($helper->activeSessions()->exists()) {
            $state['label'] = 'Assignment pending';
            $state['assignable'] = false;
        } elseif (in_array($helper->availability, ['break', 'unavailable', 'offline'], true)) {
            $state['label'] = $helper->availability === 'break' ? 'On break' : ($helper->availability === 'offline' ? 'Offline' : 'Unavailable');
            $state['assignable'] = false;
        }
        return [
            'totalSessions'=>$helper->sessions()->count(),
            'competencyScore'=>$latest ? round($latest->normalized_score * 20, 1) : null,
            'availabilityStatus'=>$state['assignable'] ? 'available' : 'unavailable',
            'availabilityLabel'=>$state['label'],
        ];
    }
}
