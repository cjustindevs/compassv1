<?php
namespace App\Http\Controllers\Adviser;
use App\Http\Controllers\Controller;
use App\Models\ConcernCategory;
use App\Services\{AdviserAnalytics,AdviserScope,SupportAudit};
use Illuminate\Http\Request;
class AdviserReportController extends Controller {
    public function index(Request $request, AdviserAnalytics $analytics) {
        $request->validate(['tab'=>'nullable|in:cases,performance,safety,activity', 'history'=>'nullable|in:current,archived,all']);
        $filters=$analytics->filters($request); $data=$analytics->report($filters);
        // One date interval and one set of controls drive every report group.
        $reportRequest = $request->duplicate();
        $reportRequest->merge(['from'=>$filters['start']->copy()->timezone('Asia/Manila')->format('Y-m-d H:i:s'),
            'to'=>$filters['end']->copy()->timezone('Asia/Manila')->format('Y-m-d H:i:s')]);
        $data['categories']=ConcernCategory::orderBy('concern_name')->get();
        $data['filterHelpers']=\App\Models\Helper::where('adviser_id',app(AdviserScope::class)->actor()->id)->orderBy('first_name')->get();
        $data['avgResponseTime']=$data['metrics']['response_minutes']===null ? 'No data' : $data['metrics']['response_minutes'].'m';
        $data['roleReport']=app(\App\Services\RoleActivityReport::class)->report($reportRequest);
        $data['tab']=$request->input('tab','cases');
        $data['sessions']=$data['roleReport']['tables'][0]['records'];
        return view('adviser.reports',$data);
    }
    public function analytics(Request $request, AdviserAnalytics $analytics) {
        $filters=$analytics->filters($request);$data=$analytics->report($filters);
        $data['categories']=ConcernCategory::orderBy('concern_name')->get();
        return view('adviser.analytics',$data);
    }
    public function export(Request $request, AdviserAnalytics $analytics) {
        $filters=$analytics->filters($request); $data=$analytics->report($filters);
        $sessions=$analytics->sessions($filters)->with(['helper','concern','evaluation'])->orderBy('id')->get();
        SupportAudit::record('report_exported',app(AdviserScope::class)->actor(),['purpose'=>'supervision_reporting','filters'=>collect($filters)->except(['start','end'])->all(),'start'=>$filters['start']->toIso8601String(),'end'=>$filters['end']->toIso8601String(),'rows'=>$sessions->count()]);
        if($request->input('format','pdf')==='pdf') return \Barryvdh\DomPDF\Facade\Pdf::loadView('adviser.report-export',$data+['sessions'=>$sessions,'startDate'=>$filters['start']->copy()->timezone('Asia/Manila'),'endDate'=>$filters['end']->copy()->timezone('Asia/Manila')])->download('compass-adviser-report.pdf');
        return response()->streamDownload(function() use($sessions,$data) {
            $file=fopen('php://output','w');
            foreach($data['metrics'] as $key=>$value) fputcsv($file,[$key,$value ?? 'No data']);
            fputcsv($file,[]); fputcsv($file,['Session','Helper','Concern','Status','Activity date (Asia/Manila)']);
            foreach($sessions as $session) {
                $concern=$session->concern?->concern_name ?? '';
                if(preg_match('/^[=+@\-]/',$concern)) $concern="'".$concern;
                $helperName=$session->helper?->full_name ?? 'Unassigned';
                if(preg_match('/^[=+@\-]/',$helperName)) $helperName="'".$helperName;
                fputcsv($file,[$session->reference_number,$helperName,$concern,$session->session_status,($session->end_time ?? $session->start_time ?? $session->submitted_at ?? $session->created_date ?? $session->created_at)->copy()->timezone('Asia/Manila')->format('Y-m-d H:i:s')]);
            }
            fclose($file);
        },'compass-adviser-report.csv',['Content-Type'=>'text/csv','Cache-Control'=>'private, no-store']);
    }
}
