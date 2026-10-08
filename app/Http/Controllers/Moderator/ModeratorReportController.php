<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Services\RoleActivityReport;
use App\Services\SupportAudit;
use Illuminate\Http\Request;

class ModeratorReportController extends Controller
{
    public function index(Request $request, RoleActivityReport $reports)
    {
        abort_unless($request->user()?->role === 'moderator', 403);

        return view('moderator.reports', ['roleReport' => $reports->report($request)]);
    }

    public function export(Request $request, RoleActivityReport $reports)
    {
        abort_unless($request->user()?->role === 'moderator', 403);
        $data = $reports->report($request, true);
        SupportAudit::record('report_exported', $request->user(), ['purpose' => 'operational_reporting', 'from' => $data['from'], 'to' => $data['to']]);

        return response()->streamDownload(function () use ($data) {
            $file = fopen('php://output', 'w');
            $write = function (array $row) use ($file) {
                fputcsv($file, array_map(fn ($value) => is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value, $row));
            };
            $write(['COMPASS Operations Report', $data['from'].' to '.$data['to'].' (Asia/Manila)']);
            foreach ($data['summary'] as $label => $value) {
                $write([$label, $value]);
            }
            foreach ($data['groups'] as $title => $values) {
                $write([]);
                $write([$title, 'Records']);
                foreach ($values as $label => $count) {
                    $write([$label, $count]);
                }
            }
            foreach ($data['tables'] as $table) {
                $write([]);
                $write([$table['title']]);
                $write($table['columns']);
                foreach ($table['records'] as $record) {
                    $write(($table['format'])($record));
                }
            }
            fclose($file);
        }, 'operations-report-'.$data['from'].'_'.$data['to'].'.csv', ['Content-Type' => 'text/csv']);
    }
}
