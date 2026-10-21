<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AdminDashboardAnalytics;
use App\Services\AnalyticsPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $period = AnalyticsPeriod::fromRequest($request);
        $technical = app(\App\Services\TechnicalDashboardSummary::class)->data($period);
        try {
            $analytics = app(AdminDashboardAnalytics::class)->data($period);
        } catch (\Throwable $exception) {
            report($exception);
            $analytics = null;
        }
        return view('admin.dashboard', [
            'analytics' => $analytics,
            'analyticsDates' => $period->dates(),
            'technical' => $technical,
            'admin' => $request->user(),
            'searchQuery' => $request->string('q')->trim()->toString(),
            'primaryStats' => app(\App\Services\DashboardIndicators::class)->admin($period, $technical),
            'recentLogs' => AuditLog::with('actor')->whereIn('module', ['auth', 'authentication', 'users', 'system', 'backups'])
                ->latest('created_at')->latest('id')->limit(6)->get(),
        ]);
    }
}
