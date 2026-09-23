<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemHealthMonitor;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class SystemHealthController extends Controller
{
    /**
     * Display the read-only infrastructure monitoring overview.
     */
    public function __invoke(Request $request, SystemHealthMonitor $monitor): View
    {
        $loadFailed = false;

        try {
            $health = $this->prepareSnapshot($monitor->snapshot());
        } catch (Throwable $exception) {
            report($exception);
            $loadFailed = true;
            $health = $this->emptySnapshot();
        }

        return view('admin.system-health.index', [
            'admin' => $request->user(),
            'health' => $health,
            'loadFailed' => $loadFailed,
        ]);
    }

    /**
     * @param  array<string, mixed>  $health
     * @return array<string, mixed>
     */
    private function prepareSnapshot(array $health): array
    {
        $services = collect($health['services'])->map(function (array $service): array {
            $status = in_array($service['status'] ?? null, ['healthy', 'warning', 'critical', 'unknown'], true)
                ? $service['status']
                : 'unknown';

            return [
                ...$service,
                'status' => $status,
                'statusLabel' => ucfirst($status),
            ];
        })->values();

        $health['services'] = $services;
        $health['overallStatus'] = $this->overallStatus($services->all());
        $health['activeIncidentCount'] = collect($health['incidents'])
            ->where('severity', 'critical')
            ->count();

        $maxStorageCategory = max(1, (int) collect($health['storage']['categories'])->max('usedGb'));
        $health['storage']['categories'] = collect($health['storage']['categories'])
            ->map(fn (array $category): array => [
                ...$category,
                'percentage' => min(100, round(($category['usedGb'] / $maxStorageCategory) * 100, 1)),
            ])
            ->all();
        $health['storage']['provisionedLabel'] = $health['storage']['provisionedGb'] >= 1024
            ? number_format($health['storage']['provisionedGb'] / 1024, 0).' TB'
            : number_format($health['storage']['provisionedGb']).' GB';
        $health['uptime']['onTrack'] = $health['uptime']['percentage'] >= $health['uptime']['target'];

        return $health;
    }

    /**
     * @param  array<int, array<string, mixed>>  $services
     * @return array{key: string, label: string}
     */
    private function overallStatus(array $services): array
    {
        $statuses = collect($services);

        if ($statuses->contains(fn (array $service): bool => ($service['core'] ?? false) && $service['status'] === 'critical')) {
            return ['key' => 'critical', 'label' => 'Critical'];
        }

        if ($statuses->contains(fn (array $service): bool => in_array($service['status'], ['warning', 'critical'], true))) {
            return ['key' => 'warning', 'label' => 'Degraded'];
        }

        if ($statuses->contains('status', 'unknown')) {
            return ['key' => 'unknown', 'label' => 'Unknown'];
        }

        return ['key' => 'healthy', 'label' => 'Operational'];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySnapshot(): array
    {
        return [
            'source' => 'unavailable',
            'services' => collect(),
            'charts' => [],
            'storage' => ['categories' => []],
            'uptime' => ['days' => []],
            'incidents' => [],
            'activeIncidentCount' => 0,
            'overallStatus' => ['key' => 'unknown', 'label' => 'Unknown'],
        ];
    }
}
