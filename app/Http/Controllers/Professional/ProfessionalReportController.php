<?php

namespace App\Http\Controllers\Professional;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsPeriod;
use App\Services\ProfessionalAnalytics;
use App\Services\SupportAudit;
use Illuminate\Http\Request;

class ProfessionalReportController extends Controller
{
    public function index(Request $request, ProfessionalAnalytics $reporting)
    {
        $professional = $request->user()->psychologyProfessional;
        abort_unless($professional, 403, 'No psychology professional profile found for this account.');
        $range = AnalyticsPeriod::fromRequest($request);
        $filters = $reporting->filters($request);
        $analytics = $reporting->data($professional->id, $range, $filters['status'] ?? null);
        $records = $reporting->records($professional->id, $range, $filters)
            ->with('session.seeker:id,generated_alias')->paginate(15)->withQueryString();

        return view('professional.reports', [
            'professional' => $professional, 'analytics' => $analytics, 'records' => $records,
            'filters' => $filters, 'dates' => $range->dates(),
            'period' => $request->input('period', 'monthly'),
        ]);
    }

    public function export(Request $request, ProfessionalAnalytics $reporting)
    {
        $professional = $request->user()->psychologyProfessional;
        abort_unless($professional, 403, 'No psychology professional profile found for this account.');
        $range = AnalyticsPeriod::fromRequest($request);
        $filters = $reporting->filters($request);
        $query = $reporting->records($professional->id, $range, $filters)->with('session.seeker:id,generated_alias');
        SupportAudit::record('report_exported', $request->user(), $range->dates() + ['purpose' => 'professional_reporting', 'activity' => $filters['activity'], 'status' => $filters['status'] ?? 'all']);

        return response()->streamDownload(function () use ($query, $range, $filters) {
            $file = fopen('php://output', 'w');
            $write = fn ($cells) => fputcsv($file, array_map(fn ($cell) => is_string($cell) && preg_match('/^[=+\-@\t\r]/', $cell) ? "'".$cell : $cell, $cells));
            $write(['COMPASS Professional Referral Report', $range->dates()['from'].' to '.$range->dates()['to'], 'Asia/Manila', $filters['activity'], $filters['status'] ?? 'All statuses']);
            $write(['Referral ID', 'Seeker Alias', 'Priority', 'Status', 'Received / legacy creation', 'Received date source', 'Accepted', 'Completed', 'Closed']);
            foreach ($query->lazy(250) as $referral) {
                $format = fn ($date) => $date?->timezone(AnalyticsPeriod::TIMEZONE)->format('Y-m-d H:i:s') ?? '';
                $completed = $referral->completed_at ?? ($referral->status === 'completed' ? $referral->closed_date : null);
                $write([$referral->id, $referral->session?->seeker?->generated_alias ?? 'Anonymous', ucfirst($referral->priority_level), ucwords(str_replace('_', ' ', $referral->status)),
                    $format($referral->professional_notified_at ?? $referral->created_at), $referral->professional_notified_at ? 'Professional notification' : 'Legacy creation date',
                    $format($referral->accepted_at), $format($completed), $format($referral->status === 'closed' ? $referral->closed_date : null)]);
            }
            fclose($file);
        }, 'professional-report-'.$range->dates()['from'].'_'.$range->dates()['to'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
