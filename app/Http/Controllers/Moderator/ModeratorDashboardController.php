<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\Helper;
use App\Models\QueueRequest;
use App\Models\Session;
use App\Services\DashboardOverview;
use App\Services\HelperEligibilityService;
use App\Services\ModeratorActivity;
use App\Services\ModeratorEmergencyCases;
use App\Services\StaleQueueRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ModeratorDashboardController extends Controller
{
    public function index(Request $request)
    {
        app(StaleQueueRequests::class)->expire();
        $stats = [
            'queue_waiting' => QueueRequest::where('is_test_record',false)->whereNull('duplicate_of_id')->where('request_status', 'waiting')->count(),
            'queue_assigned' => QueueRequest::where('is_test_record',false)->whereNull('duplicate_of_id')->where('request_status', 'assigned')->count(),
            'active_sessions' => Session::where('is_test_record',false)->whereNull('duplicate_of_id')->where('session_status', 'active')->count(),
            'emergency_count' => app(ModeratorEmergencyCases::class)->active()->count(),
            'available_helpers' => $this->availableHelperCount(),
            'unserved' => QueueRequest::where('is_test_record',false)->whereNull('duplicate_of_id')->where('request_status', 'waiting')
                ->where('request_date', '<', now()->subMinutes(30))
                ->count(),
        ];

        $recentActivity = $this->getRecentActivity();

        $overview = app(DashboardOverview::class)->forUser($request->user());
        $overviewSignature = hash('sha256', json_encode($overview));

        return view('moderator.dashboard', compact(
            'overviewSignature',
            'overview',
            'stats',
            'recentActivity'
        ));
    }

    /**
     * One operational activity feed reused by the Dashboard and Reports.
     */
    private function getRecentActivity(): array
    {
        return app(ModeratorActivity::class)->query()->orderByDesc('occurred_at')->orderByDesc('id')->limit(8)->get()->map(fn ($event) => [
            'title' => ucwords(str_replace('_', ' ', $event->action)),
            'message' => ucwords(str_replace('_', ' ', $event->record_type)).' #'.$event->record_id.' | '.$event->actor,
            'time' => Carbon::parse($event->occurred_at)->timezone('Asia/Manila')->format('M d, g:i A'),
            'link' => route('moderator.reports', ['tab' => 'activity']),
        ])->all();
    }

    public function stats(): JsonResponse
    {
        $overview = app(DashboardOverview::class)->forUser(request()->user());

        return response()->json([
            'overview_html' => view('moderator.partials.dashboard-analytics', compact('overview'))->render(),
            'queue_waiting' => QueueRequest::where('is_test_record',false)->whereNull('duplicate_of_id')->where('request_status', 'waiting')->count(),
            'queue_assigned' => QueueRequest::where('is_test_record',false)->whereNull('duplicate_of_id')->where('request_status', 'assigned')->count(),
            'active_sessions' => Session::where('is_test_record',false)->whereNull('duplicate_of_id')->where('session_status', 'active')->count(),
            'emergency_open' => app(ModeratorEmergencyCases::class)->active()->count(),
            'overview_signature' => hash('sha256', json_encode($overview)),
            'helpers_available' => $this->availableHelperCount(),
            'unserved' => QueueRequest::where('is_test_record',false)->whereNull('duplicate_of_id')->where('request_status', 'waiting')
                ->where('request_date', '<', now()->subMinutes(30))
                ->count(),
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Helpers shown as Available must also pass the eligibility rules
     * (current readiness, duty shift, capacity); a stored status alone can
     * lag behind readiness expiry.
     */
    private function availableHelperCount(): int
    {
        return app(HelperEligibilityService::class)
            ->countAvailable(Helper::all());
    }
}
