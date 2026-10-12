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
        try {
            $analytics = app(AdminDashboardAnalytics::class)->data($period);
        } catch (\Throwable $exception) {
            report($exception);
            $analytics = null;
        }
        return view('admin.dashboard', [
            'analytics' => $analytics,
            'analyticsDates' => $period->dates(),
            'admin' => $request->user(),
            'searchQuery' => $request->string('q')->trim()->toString(),
            'primaryStats' => [
                ['label' => 'Total Users', 'value' => number_format(User::count()), 'detail' => 'Registered accounts', 'icon' => 'users'],
                ['label' => 'Active Accounts', 'value' => number_format(User::where('is_active', true)->count()), 'detail' => 'Enabled accounts, including users who are offline', 'icon' => 'user-check'],
                ['label' => 'Unverified Accounts', 'value' => number_format(User::whereNull('email_verified_at')->count()), 'detail' => 'Accounts awaiting email verification', 'icon' => 'shield'],
            ],
            'recentLogs' => AuditLog::with('actor')->whereIn('module', ['auth', 'authentication', 'users', 'system', 'backups'])
                ->latest('created_at')->latest('id')->limit(6)->get(),
        ]);
    }
}
