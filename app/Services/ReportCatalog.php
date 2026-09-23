<?php

namespace App\Services;

use App\Models\HelperCompetencyHistory;
use App\Models\IncidentReport;
use App\Models\Referral;
use App\Models\Session;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ReportCatalog
{
    /**
     * Return the predefined administrator report catalog with live source
     * activity metadata. Detailed report generation remains intentionally
     * unavailable until a reviewed generator and authorization policy exist.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function reports(): Collection
    {
        return collect([
            $this->definition(
                'monthly-session',
                'Monthly session report',
                'sessions',
                'Volume, ratings, and outcomes across all peer sessions.',
                'Counseling sessions',
                [Session::class]
            ),
            $this->definition(
                'referral-outcomes',
                'Referral outcomes',
                'referrals',
                'Referral acceptance, resolution, and follow-up trends.',
                'Referrals',
                [Referral::class]
            ),
            $this->definition(
                'helper-competency-growth',
                'Helper competency growth',
                'competencies',
                'Radar and score movement by cohort.',
                'Helper competency evaluations',
                [HelperCompetencyHistory::class]
            ),
            $this->definition(
                'performance-benchmarks',
                'Performance benchmarks',
                'performance',
                'Response time, escalation rate, and workload.',
                'Sessions and referrals',
                [Session::class, Referral::class]
            ),
            $this->definition(
                'users-activity',
                'Users & activity',
                'users',
                'Registrations, active users, and role distribution.',
                'User accounts',
                [User::class]
            ),
            $this->definition(
                'emergency-incident-log',
                'Emergency incident log',
                'analytics',
                'Escalations, response time, and closure notes.',
                'Incident reports',
                [IncidentReport::class],
                true
            ),
        ]);
    }

    /**
     * Report operations remain unavailable until protected generation,
     * print, export, and file-delivery services are implemented.
     *
     * @return array<string, mixed>
     */
    public function capabilities(): array
    {
        return [
            'view' => true,
            'generate' => false,
            'export' => false,
            'print' => false,
            'download' => false,
            'formats' => [],
        ];
    }

    /**
     * @param  array<int, class-string>  $sourceModels
     * @return array<string, mixed>
     */
    private function definition(
        string $id,
        string $title,
        string $category,
        string $description,
        string $sourceLabel,
        array $sourceModels,
        bool $sensitive = false
    ): array {
        $activity = $this->sourceActivity($sourceModels);

        return [
            'id' => $id,
            'title' => $title,
            'category' => $category,
            'description' => $description,
            'sourceLabel' => $sourceLabel,
            'sourceRecordCount' => $activity['count'],
            'updatedAt' => $activity['updatedAt'],
            'sensitive' => $sensitive,
            'viewable' => true,
            'exportable' => false,
            'printable' => false,
            'downloadable' => false,
            'downloadUrl' => null,
            'availableFormats' => [],
        ];
    }

    /**
     * @param  array<int, class-string>  $sourceModels
     * @return array{count: int, updatedAt: ?CarbonImmutable}
     */
    private function sourceActivity(array $sourceModels): array
    {
        $count = 0;
        $updatedAt = null;

        foreach ($sourceModels as $sourceModel) {
            $query = $sourceModel::query();
            $count += $query->count();
            $latest = $query->max('updated_at');

            if ($latest !== null) {
                $candidate = CarbonImmutable::parse($latest);
                $updatedAt = $updatedAt === null || $candidate->isAfter($updatedAt)
                    ? $candidate
                    : $updatedAt;
            }
        }

        return compact('count', 'updatedAt');
    }
}
