<?php

namespace App\Services;

use App\Models\Helper;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class HelperWorkload
{
    public function page(Request $request): LengthAwarePaginator
    {
        abort_unless($request->user()?->is_active && in_array($request->user()->role, ['adviser', 'moderator'], true), 403);
        $data = $request->validate(['workload_page'=>'nullable|integer|min:1', 'workload_search'=>'nullable|string|max:80', 'workload_status'=>'nullable|in:all,available,busy,unavailable']);
        $query = Helper::with('user', 'adviser.user')->when($request->user()->role === 'adviser', fn ($q) => $q->where('adviser_id', app(AdviserScope::class)->actor()->id))
            ->orderBy('first_name')->orderBy('id');
        foreach (array_slice(preg_split('/\s+/', trim($data['workload_search'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), 0, 6) as $term) {
            $query->where(fn ($q) => $q->where('first_name', 'like', '%'.$term.'%')->orWhere('last_name', 'like', '%'.$term.'%'));
        }
        $row = function (Helper $helper) {
            $state = app(HelperEligibilityService::class)->status($helper);
            $assignments = $helper->assignmentBlockers()->count();
            return ['id'=>$helper->id, 'name'=>$helper->full_name, 'status'=>$state['label'], 'reason'=>$state['reason'],
                'status_key'=>$state['assignable'] ? 'available' : ($assignments ? 'busy' : 'unavailable'),
                'assignments'=>$assignments, 'used'=>$helper->currentAssignedSessionsCount(), 'limit'=>Helper::MAX_SESSIONS_PER_SHIFT,
                'remaining'=>$helper->getRemainingCapacity()];
        };
        $status = $data['workload_status'] ?? 'all';
        if ($status !== 'all') {
            $rows = $query->get()->map($row)->where('status_key', $status)->values();
            $page = (int) ($data['workload_page'] ?? 1);
            $result = new LengthAwarePaginator($rows->forPage($page, 10)->values(), $rows->count(), 10, $page, ['pageName'=>'workload_page']);
        } else {
            $result = $query->paginate(10, ['*'], 'workload_page');
            $result->setCollection($result->getCollection()->map($row));
        }
        return $result->withPath(route($request->user()->role === 'adviser' ? 'adviser.schedule' : 'moderator.schedules'))
            ->appends($request->except('workload_page'))->fragment('helperWorkload');
    }
}
