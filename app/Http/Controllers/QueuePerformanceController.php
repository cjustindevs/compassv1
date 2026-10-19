<?php

namespace App\Http\Controllers;

use App\Models\ConcernCategory;
use App\Services\QueuePerformance;
use Illuminate\Http\Request;

class QueuePerformanceController extends Controller
{
    public function index(Request $request, QueuePerformance $performance)
    {
        return response()->view('queue-performance', $performance->report($request) + ['concerns'=>ConcernCategory::orderBy('concern_name')->get()])
            ->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request, QueuePerformance $performance)
    {
        $data = $performance->report($request);
        \App\Services\SupportAudit::record('queue_performance_exported', $request->user(), ['from'=>$data['period']->dates()['from'],'to'=>$data['period']->dates()['to'],'filters'=>$data['filters']]);
        return response()->streamDownload(function () use ($data) {
            $file = fopen('php://output','w');
            fputcsv($file,['COMPASS queue performance', $data['asOf']->timezone('Asia/Manila')->format('Y-m-d H:i:s').' PHT']);
            fputcsv($file,['Metric','Value','Sample / denominator']);
            foreach ($data['cards'] as $row) fputcsv($file,[$row['label'],$row['value'],$row['sample']]);
            fputcsv($file,[]);fputcsv($file,['Data quality']);
            foreach ($data['quality'] as $key=>$value) fputcsv($file,[$key,$value ?? 'No data']);
            fclose($file);
        }, 'compass-queue-performance.csv', ['Content-Type'=>'text/csv','Cache-Control'=>'private, no-store']);
    }
}
