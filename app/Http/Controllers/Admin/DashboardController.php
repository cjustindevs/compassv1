<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the system administrator overview.
     */
    public function __invoke(Request $request): View
    {
        return view('admin.dashboard', [
            'admin' => $request->user(),
            'searchQuery' => $request->string('q')->trim()->toString(),
            ...$this->mockDashboardData(),
        ]);
    }

    /**
     * Dashboard demo data, isolated here for later service/API replacement.
     *
     * @return array<string, mixed>
     */
    private function mockDashboardData(): array
    {
        return [
            'primaryStats' => [
                [
                    'label' => 'Total Users',
                    'value' => '486',
                    'trend' => '+12',
                    'detail' => 'new this week',
                    'icon' => 'users',
                    'tone' => 'blue',
                ],
                [
                    'label' => 'Online Users',
                    'value' => '142',
                    'detail' => 'active session',
                    'icon' => 'user-check',
                    'tone' => 'green',
                ],
                [
                    'label' => 'System Health',
                    'value' => 'Healthy',
                    'detail' => '0 critical, 2 warnings',
                    'icon' => 'heart-pulse',
                    'tone' => 'green',
                ],
                [
                    'label' => 'Storage',
                    'value' => '612 GB',
                    'trend' => '+38 GB',
                    'detail' => 'of 1 TB (61%)',
                    'icon' => 'storage',
                    'tone' => 'orange',
                ],
            ],
            'systemStatuses' => [
                [
                    'label' => 'Server',
                    'value' => 'Operational',
                    'detail' => '12 ms',
                    'icon' => 'server',
                    'tone' => 'green',
                ],
                [
                    'label' => 'Database',
                    'value' => 'Operational',
                    'detail' => '9 ms',
                    'icon' => 'database',
                    'tone' => 'green',
                ],
                [
                    'label' => 'API',
                    'value' => 'Degraded',
                    'detail' => '240 ms',
                    'icon' => 'network',
                    'tone' => 'orange',
                ],
                [
                    'label' => 'Active Sessions',
                    'value' => '142 live',
                    'detail' => '8%',
                    'trend' => 'up',
                    'icon' => 'activity',
                    'tone' => 'blue',
                ],
            ],
            'charts' => [
                'userGrowth' => [
                    'labels' => ['Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    'max' => 400,
                    'ticks' => [400, 300, 200, 100, 0],
                    'series' => [
                        [
                            'name' => 'Users',
                            'color' => '#20bd68',
                            'values' => [118, 176, 238, 257, 321, 338, 400],
                            'fill' => true,
                        ],
                    ],
                ],
                'systemUsage' => [
                    'labels' => ['00:00', '', '', '', '', '', '', '', '', '', '04:00', '', '', '', '', '', '', '', '', '08:00'],
                    'max' => 60,
                    'ticks' => [60, 45, 30, 15, 0],
                    'series' => [
                        [
                            'name' => 'CPU %',
                            'color' => '#22b85f',
                            'values' => [24, 25, 39, 39, 39, 32, 34, 18, 17, 15, 12, 6, 9, 5, 7, 12, 22, 36, 30, 39],
                        ],
                        [
                            'name' => 'Memory %',
                            'color' => '#2aa8ef',
                            'values' => [55, 54, 58, 50, 48, 46, 42, 39, 36, 33, 29, 34, 29, 29, 29, 35, 31, 38, 46, 48],
                        ],
                    ],
                ],
                'loginStatistics' => [
                    'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    'max' => 450,
                    'ticks' => [450, 300, 150, 0],
                    'series' => [
                        [
                            'name' => 'success',
                            'color' => '#20bd68',
                            'values' => [345, 382, 397, 371, 354, 181, 149],
                        ],
                        [
                            'name' => 'failed',
                            'color' => '#f05252',
                            'values' => [8, 11, 9, 13, 10, 5, 4],
                        ],
                    ],
                ],
            ],
            'activities' => [
                [
                    'message' => 'New user Karla Uy provisioned by A. Santos',
                    'time' => '2 min ago',
                    'icon' => 'user-plus',
                    'tone' => 'green',
                ],
                [
                    'message' => 'API latency exceeded 250ms threshold (p95)',
                    'time' => '18 min ago',
                    'icon' => 'warning',
                    'tone' => 'orange',
                ],
                [
                    'message' => "Announcement 'Crisis protocol v2.4' published to all users",
                    'time' => '42 min ago',
                    'icon' => 'check-circle',
                    'tone' => 'green',
                ],
                [
                    'message' => '5 failed login attempts on account francisa@pdaf.gov',
                    'time' => '1 hr ago',
                    'icon' => 'alert-circle',
                    'tone' => 'red',
                ],
                [
                    'message' => 'Auto-scaled worker pool from 4 → 6 instances',
                    'time' => '2 hr ago',
                    'icon' => 'cpu',
                    'tone' => 'blue',
                ],
                [
                    'message' => 'Bulk role reassignment: 12 users updated',
                    'time' => '3 hr ago',
                    'icon' => 'users',
                    'tone' => 'blue',
                ],
            ],
            'recentLogs' => [
                [
                    'title' => 'User role updated',
                    'actor' => 'A. Santos',
                    'area' => 'Users',
                    'datetime' => '2026-07-01 08:00:12',
                    'ip' => '10.24.0.0',
                    'status' => 'Info',
                    'tone' => 'blue',
                ],
                [
                    'title' => 'Password reset',
                    'actor' => 'B. Cruz',
                    'area' => 'Auth',
                    'datetime' => '2026-07-02 09:03:12',
                    'ip' => '10.24.1.7',
                    'status' => 'Info',
                    'tone' => 'blue',
                ],
                [
                    'title' => 'Announcement published',
                    'actor' => 'C. Reyes',
                    'area' => 'Announcements',
                    'datetime' => '2026-07-03 10:06:12',
                    'ip' => '10.24.2.14',
                    'status' => 'Warning',
                    'tone' => 'orange',
                ],
                [
                    'title' => 'User deactivated',
                    'actor' => 'System',
                    'area' => 'Reports',
                    'datetime' => '2026-07-04 11:09:12',
                    'ip' => '10.24.3.21',
                    'status' => 'Critical',
                    'tone' => 'red',
                ],
                [
                    'title' => 'Permission changed',
                    'actor' => 'A. Santos',
                    'area' => 'Roles',
                    'datetime' => '2026-07-05 12:12:12',
                    'ip' => '10.24.4.28',
                    'status' => 'Info',
                    'tone' => 'blue',
                ],
                [
                    'title' => 'Report exported',
                    'actor' => 'B. Cruz',
                    'area' => 'System',
                    'datetime' => '2026-07-06 13:15:12',
                    'ip' => '10.24.5.35',
                    'status' => 'Info',
                    'tone' => 'blue',
                ],
            ],
        ];
    }
}
