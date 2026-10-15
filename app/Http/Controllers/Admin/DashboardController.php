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
            'primaryStats' => [
                ['label' => 'Active Accounts', 'value' => number_format(User::where('is_active', true)->count()), 'detail' => 'Enabled accounts, including users who are offline', 'icon' => 'user-check', 'link'=>route('admin.users', ['status'=>'active'])],
                ['label' => 'Current System Health', 'value' => $technical['health'], 'detail' => 'Current monitoring status; not historical uptime', 'icon' => 'shield', 'link'=>route('admin.system-health')],
                ['label' => 'Security Events', 'value' => $technical['security_events'], 'detail' => 'Recorded security events in selected period', 'icon' => 'shield', 'link'=>route('admin.audit-logs')],
                ['label' => 'Latest Backup Status', 'value' => $technical['backup'], 'detail' => 'Configured backup provider records', 'icon' => 'shield', 'link'=>route('admin.backup-restore')],
            ],
            'recentLogs' => AuditLog::with('actor')->whereIn('module', ['auth', 'authentication', 'users', 'system', 'backups'])
                ->latest('created_at')->latest('id')->limit(6)->get(),
        ]);
    }
}
