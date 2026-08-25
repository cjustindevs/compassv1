<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class SystemHealthMonitor
{
    /**
     * Return the current monitoring snapshot.
     *
     * No infrastructure monitoring provider is configured in COMPASS yet.
     * Keep reference data centralized here so the view can be replaced with
     * a real provider without changing page components or inventing an API.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'source' => 'preview',
            'updatedAt' => CarbonImmutable::now(),
            'services' => $this->services(),
            'charts' => $this->charts(),
            'storage' => [
                'usedGb' => 612,
                'provisionedGb' => 1024,
                'categories' => [
                    ['label' => 'Referral attachments', 'usedGb' => 184, 'tone' => 'blue'],
                    ['label' => 'Clinical notes archive', 'usedGb' => 212, 'tone' => 'green'],
                    ['label' => 'Voice recordings', 'usedGb' => 148, 'tone' => 'cyan'],
                    ['label' => 'System logs', 'usedGb' => 68, 'tone' => 'orange'],
                ],
            ],
            'uptime' => [
                'percentage' => 99.98,
                'target' => 99.9,
                'days' => $this->uptimeHistory(),
            ],
            'incidents' => [
                [
                    'id' => 'voice-call-1',
                    'service' => 'Voice Call',
                    'severity' => 'critical',
                    'message' => 'Outbound call failed — retry queue exhausted',
                    'time' => '14:12',
                ],
                [
                    'id' => 'api-1',
                    'service' => 'API',
                    'severity' => 'warning',
                    'message' => 'p95 latency 260ms (SLO 200ms)',
                    'time' => '13:47',
                ],
                [
                    'id' => 'cpu-1',
                    'service' => 'CPU',
                    'severity' => 'warning',
                    'message' => 'Worker-3 hit 92% for 4 minutes',
                    'time' => '12:31',
                ],
                [
                    'id' => 'database-1',
                    'service' => 'DB',
                    'severity' => 'critical',
                    'message' => 'Slow query exceeded 8s on referral events',
                    'time' => '10:04',
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function services(): array
    {
        return [
            ['id' => 'server', 'name' => 'Server Status', 'status' => 'healthy', 'metric' => 'Uptime 99.99%', 'icon' => 'server', 'core' => true],
            ['id' => 'database', 'name' => 'Database', 'status' => 'healthy', 'metric' => 'Uptime 99.98%', 'icon' => 'database', 'core' => true],
            ['id' => 'authentication', 'name' => 'Authentication', 'status' => 'healthy', 'metric' => 'Uptime 100%', 'icon' => 'lock', 'core' => true],
            ['id' => 'api', 'name' => 'API', 'status' => 'warning', 'metric' => 'p95 latency 240ms', 'icon' => 'network', 'core' => true],
            ['id' => 'storage', 'name' => 'Storage', 'status' => 'healthy', 'metric' => '61% used', 'icon' => 'storage', 'core' => true],
            ['id' => 'memory', 'name' => 'Memory', 'status' => 'healthy', 'metric' => '54% avg', 'icon' => 'memory', 'core' => false],
            ['id' => 'cpu', 'name' => 'CPU', 'status' => 'warning', 'metric' => '82% peak', 'icon' => 'cpu', 'core' => false],
            ['id' => 'queue', 'name' => 'Queue Service', 'status' => 'healthy', 'metric' => '0 backlog', 'icon' => 'layers', 'core' => false],
            ['id' => 'notifications', 'name' => 'Notification Service', 'status' => 'healthy', 'metric' => 'Uptime 99.97%', 'icon' => 'bell', 'core' => false],
            ['id' => 'voice', 'name' => 'Voice Call Service', 'status' => 'critical', 'metric' => '3 failed calls', 'icon' => 'phone', 'core' => false],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function charts(): array
    {
        $labels = ['00:00', '', '', '', '', '', '', '', '', '', '04:00', '', '', '', '', '', '', '', '', '08:00'];

        return [
            'cpu' => [
                'labels' => $labels,
                'max' => 60,
                'ticks' => [60, 45, 30, 15, 0],
                'series' => [[
                    'name' => 'CPU %',
                    'color' => '#2f6fed',
                    'values' => [25, 27, 41, 41, 40, 33, 35, 21, 19, 17, 13, 8, 10, 7, 9, 14, 23, 36, 31, 40],
                    'fill' => true,
                ]],
            ],
            'memory' => [
                'labels' => $labels,
                'max' => 60,
                'ticks' => [60, 45, 30, 15, 0],
                'series' => [[
                    'name' => 'Memory %',
                    'color' => '#2ba9ee',
                    'values' => [54, 53, 57, 48, 46, 46, 42, 38, 34, 30, 28, 33, 33, 33, 38, 34, 40, 36, 45, 45, 52, 54],
                    'fill' => true,
                ]],
            ],
            'api' => [
                'labels' => $labels,
                'max' => 1400,
                'ticks' => [1400, 1050, 700, 350, 0],
                'series' => [
                    [
                        'name' => 'p50',
                        'color' => '#2f6fed',
                        'values' => [820, 1100, 1320, 1360, 1230, 1010, 690, 680, 530, 430, 470, 700, 850, 980, 1090, 1350, 1260, 980, 730, 590, 480, 460, 560],
                        'dash' => '10 8',
                    ],
                    [
                        'name' => 'p95',
                        'color' => '#f79009',
                        'values' => [95, 98, 105, 112, 108, 104, 101, 99, 96, 92, 90, 91, 94, 97, 102, 108, 112, 116, 110, 107, 105, 111, 118],
                        'dash' => '7 7',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function uptimeHistory(): array
    {
        $days = array_fill(0, 60, 'operational');
        $days[23] = 'degraded';
        $days[42] = 'outage';
        $days[50] = 'degraded';

        return $days;
    }
}
