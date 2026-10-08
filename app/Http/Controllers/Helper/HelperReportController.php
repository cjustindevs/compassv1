<?php

namespace App\Http\Controllers\Helper;

use App\Http\Controllers\Controller;
use App\Models\ConcernCategory;
use App\Services\HelperViewDateRange;
use App\Services\RoleActivityReport;
use App\Services\SupportAudit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class HelperReportController extends Controller
{
    private function data(Request $request, RoleActivityReport $reports, bool $export = false): array
    {
        abort_unless($request->user()?->role === 'helper' && $request->user()->is_active && $request->user()->helper, 403);
        app(HelperViewDateRange::class)->apply($request);

        return $reports->report($request, $export);
    }

    public function index(Request $request, RoleActivityReport $reports)
    {
        return view('helper.reports', ['roleReport' => $this->data($request, $reports), 'categories' => ConcernCategory::orderBy('concern_name')->get()]);
    }

    public function export(Request $request, RoleActivityReport $reports)
    {
        $request->validate(['format' => 'required|in:pdf,csv']);
        $roleReport = $this->data($request, $reports, true);
        SupportAudit::record('helper_report_exported', $request->user()->helper, ['from' => $roleReport['from'], 'to' => $roleReport['to'], 'case_status' => $request->input('case_status'), 'concern_id' => $request->input('concern_id'), 'format' => $request->input('format')]);
        if ($request->input('format') === 'pdf') {
            return Pdf::loadView('helper.report-export', compact('roleReport'))->setPaper('a4', 'landscape')->download('compass-helper-report.pdf');
        }

        return response()->streamDownload(function () use ($roleReport) {
            $out = fopen('php://output', 'w');
            $safe = fn ($value) => preg_match('/^[\s]*[=+@\-]/', (string) $value) ? "'".$value : $value;
            fputcsv($out, ['COMPASS - My Service Report', $roleReport['from'].' to '.$roleReport['to'].' (Asia/Manila)']);
            foreach ($roleReport['summary'] as $label => $value) {
                fputcsv($out, [$label, $value]);
            }
            foreach ($roleReport['tables'] as $table) {
                fputcsv($out, []);
                fputcsv($out, [$table['title']]);
                fputcsv($out, $table['columns']);
                foreach ($table['records'] as $record) {
                    fputcsv($out, array_map($safe, ($table['format'])($record)));
                }
            }
            fclose($out);
        }, 'compass-helper-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
