<?php
namespace App\Services;
use App\Models\{Helper, HelperCompetencyHistory};
class HelperSidebarStats
{
    public function forHelper(Helper $helper): array
    {
        $latest = HelperCompetencyHistory::where('helper_id', $helper->id)->latest('evaluation_date')->latest('id')->first();
        $state = app(HelperEligibilityService::class)->status($helper);
        return [
            'totalSessions'=>$helper->sessions()->count(),
            'competencyScore'=>$latest ? round($latest->normalized_score * 20, 1) : null,
            'availabilityStatus'=>$state['assignable'] ? 'available' : 'unavailable',
            'availabilityLabel'=>$state['label'],
        ];
    }
}
