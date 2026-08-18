<?php

namespace App\Http\Controllers\Professional;

use App\Http\Controllers\Controller;
use App\Models\ProfessionalNote;
use App\Models\Referral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfessionalReportController extends Controller
{
    public function index(Request $request)
    {
        $professional = Auth::user()->psychologyProfessional;

        if (! $professional) {
            abort(403, 'No psychology professional profile found for this account.');
        }

        $period = $request->get('period', 'monthly');

        if (! in_array($period, ['weekly', 'monthly', 'quarterly', 'yearly'], true)) {
            $period = 'monthly';
        }

        [$startDate, $endDate] = $this->getDateRange($period);

        // Metrics for the selected period
        $totalReferrals = Referral::where('professional_id', $professional->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $accepted = Referral::where('professional_id', $professional->id)
            ->whereIn('status', [...Referral::ACTIVE_STATUSES, ...Referral::COMPLETED_STATUSES])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $completed = Referral::where('professional_id', $professional->id)
            ->whereIn('status', Referral::COMPLETED_STATUSES)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $acceptanceRate = $totalReferrals > 0 ? (int) round(($accepted / $totalReferrals) * 100) : 0;

        // Average response time (hours) for the period
        $avgResponseHours = $this->averageResponseHours($professional->id, $startDate, $endDate);

        // Monthly referral trends (real data, last 6 months)
        $monthlyTrends = $this->getMonthlyTrends($professional->id);

        // Case outcomes distribution (all-time)
        $outcomes = [
            'completed' => Referral::where('professional_id', $professional->id)
                ->where('status', Referral::STATUS_COMPLETED)->count(),
            'closed' => Referral::where('professional_id', $professional->id)
                ->where('status', Referral::STATUS_CLOSED)->count(),
            'declined' => Referral::where('professional_id', $professional->id)
                ->where('status', Referral::STATUS_DECLINED)->count(),
            'active' => Referral::where('professional_id', $professional->id)
                ->whereIn('status', Referral::ACTIVE_STATUSES)->count(),
        ];

        // Intervention type breakdown (all-time)
        $interventions = ProfessionalNote::where('professional_id', $professional->id)
            ->selectRaw('intervention_type, COUNT(*) as total')
            ->whereNotNull('intervention_type')
            ->groupBy('intervention_type')
            ->pluck('total', 'intervention_type');

        // Recent activity for the period report
        $recentReferrals = Referral::with(['session', 'session.seeker'])
            ->where('professional_id', $professional->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('professional.reports', compact(
            'professional',
            'period',
            'totalReferrals',
            'accepted',
            'completed',
            'acceptanceRate',
            'avgResponseHours',
            'monthlyTrends',
            'outcomes',
            'interventions',
            'recentReferrals'
        ));
    }

    public function export(Request $request)
    {
        $period = $request->get('period', 'monthly');

        if (! in_array($period, ['weekly', 'monthly', 'quarterly', 'yearly'], true)) {
            $period = 'monthly';
        }

        [$startDate, $endDate] = $this->getDateRange($period);

        $referrals = Referral::with(['session', 'session.seeker'])
            ->where('professional_id', Auth::user()->psychologyProfessional->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderByDesc('created_at')
            ->get();

        $filename = 'professional-report-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($referrals) {
            $file = fopen('php://output', 'w');

            fputcsv($file, ['Referral ID', 'Seeker Alias', 'Priority', 'Status', 'Referral Date', 'Closed Date']);

            foreach ($referrals as $referral) {
                fputcsv($file, [
                    $referral->id,
                    $referral->session?->seeker?->generated_alias ?? 'Anonymous',
                    ucfirst($referral->priority_level),
                    ucfirst(str_replace('_', ' ', $referral->status)),
                    $referral->created_at?->format('Y-m-d') ?? '',
                    $referral->closed_date?->format('Y-m-d') ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function getDateRange(string $period): array
    {
        $endDate = now();

        $startDate = match ($period) {
            'weekly' => now()->subDays(7),
            'quarterly' => now()->subDays(90),
            'yearly' => now()->subDays(365),
            default => now()->subDays(30),
        };

        return [$startDate, $endDate];
    }

    /**
     * Real monthly referral data for the last 6 months (grouped in PHP so it
     * works on any database driver).
     */
    private function getMonthlyTrends(int $professionalId): array
    {
        $months = collect(range(5, 0))
            ->map(fn (int $offset) => now()->startOfMonth()->subMonths($offset))
            ->map(function ($month) {
                return [
                    'key' => $month->format('Y-m'),
                    'label' => $month->format('M'),
                ];
            });

        $rows = Referral::selectRaw('created_at, status')
            ->where('professional_id', $professionalId)
            ->where('created_at', '>=', $months->first()['key'] . '-01')
            ->get()
            ->groupBy(fn (Referral $referral) => $referral->created_at?->format('Y-m'));

        return $months->map(function (array $month) use ($rows) {
            $bucket = $rows->get($month['key'], collect());

            return [
                'month' => $month['label'],
                'referrals' => $bucket->count(),
                'completed' => $bucket->whereIn('status', Referral::COMPLETED_STATUSES)->count(),
            ];
        })->all();
    }

    private function averageResponseHours(int $professionalId, $startDate, $endDate): int
    {
        $hours = Referral::where('professional_id', $professionalId)
            ->whereIn('status', [...Referral::ACTIVE_STATUSES, ...Referral::COMPLETED_STATUSES])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->filter(fn (Referral $referral) => $referral->created_at && $referral->updated_at)
            ->filter(fn (Referral $referral) => $referral->updated_at->greaterThan($referral->created_at))
            ->map(fn (Referral $referral) => $referral->created_at->diffInHours($referral->updated_at));

        if ($hours->isEmpty()) {
            return 0;
        }

        return (int) round($hours->avg());
    }
}
