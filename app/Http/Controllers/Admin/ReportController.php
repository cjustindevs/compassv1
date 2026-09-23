<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ReportController extends Controller
{
    /**
     * Display the protected administrator report catalog.
     */
    public function __invoke(Request $request, ReportCatalog $reportCatalog): View
    {
        $loadFailed = false;

        try {
            $capabilities = $reportCatalog->capabilities();
            $reports = $reportCatalog->reports()
                ->map(fn (array $report): array => $this->reportViewModel($report, $capabilities))
                ->values();
        } catch (Throwable $exception) {
            report($exception);
            $loadFailed = true;
            $capabilities = $this->unavailableCapabilities();
            $reports = collect();
        }

        return view('admin.reports.index', [
            'admin' => $request->user(),
            'reports' => $reports,
            'capabilities' => $capabilities,
            'loadFailed' => $loadFailed,
            'searchQuery' => Str::limit($request->string('q')->trim()->toString(), 120, ''),
            'categories' => $reports
                ->pluck('categoryLabel', 'category')
                ->unique()
                ->all(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $capabilities
     * @return array<string, mixed>
     */
    private function reportViewModel(array $report, array $capabilities): array
    {
        $updatedAt = $report['updatedAt'] ?? null;
        $category = in_array($report['category'] ?? null, [
            'sessions',
            'referrals',
            'competencies',
            'performance',
            'users',
            'analytics',
        ], true) ? $report['category'] : 'analytics';

        return [
            ...$report,
            'category' => $category,
            'categoryLabel' => Str::upper($category),
            'updatedAtTimestamp' => $updatedAt?->getTimestamp(),
            'updatedAtIso' => $updatedAt?->toIso8601String(),
            'updatedAtTitle' => $updatedAt?->format('F j, Y \a\t h:i A'),
            'updatedLabel' => $updatedAt ? 'Updated '.$updatedAt->format('M j') : 'Awaiting source data',
            'viewable' => (bool) ($report['viewable'] ?? false) && (bool) ($capabilities['view'] ?? false),
            'exportable' => (bool) ($report['exportable'] ?? false) && (bool) ($capabilities['export'] ?? false),
            'printable' => (bool) ($report['printable'] ?? false) && (bool) ($capabilities['print'] ?? false),
            'downloadable' => (bool) ($report['downloadable'] ?? false)
                && (bool) ($capabilities['download'] ?? false)
                && filled($report['downloadUrl'] ?? null),
            'downloadUrl' => (bool) ($capabilities['download'] ?? false)
                ? ($report['downloadUrl'] ?? null)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailableCapabilities(): array
    {
        return [
            'view' => false,
            'generate' => false,
            'export' => false,
            'print' => false,
            'download' => false,
            'formats' => [],
        ];
    }
}
