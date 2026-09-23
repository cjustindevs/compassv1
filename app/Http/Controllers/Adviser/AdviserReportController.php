<?php
namespace App\Http\Controllers\Adviser;
use App\Http\Controllers\Controller;
use App\Models\ConcernCategory;
use App\Services\{AdviserAnalytics,AdviserScope,SupportAudit};
use Illuminate\Http\Request;
class AdviserReportController extends Controller {
    public function index(Request $request, AdviserAnalytics $analytics) {
        $filters=$analytics->filters($request); $data=$analytics->report($filters);
        $data['sessions']=$analytics->sessions($filters)->with(['helper','concern'])->latest('id')->paginate(15)->withQueryString();
        $data['categories']=ConcernCategory::orderBy('concern_name')->get();
        $data['avgResponseTime']=$data['metrics']['response_minutes']===null ? 'No data' : $data['metrics']['response_minutes'].'m';
        return view('adviser.reports',$data);
    }
    public function export(Request $request, AdviserAnalytics $analytics) {
        $filters=$analytics->filters($request); $data=$analytics->report($filters);
        $sessions=$analytics->sessions($filters)->with(['helper','concern','evaluation'])->orderBy('id')->get();
        SupportAudit::record('report_exported',app(AdviserScope::class)->actor(),['purpose'=>'supervision_reporting','filters'=>collect($filters)->except(['start','end'])->all(),'start'=>$filters['start']->toIso8601String(),'end'=>$filters['end']->toIso8601String(),'rows'=>$sessions->count()]);
        if($request->input('format','pdf')==='pdf') return \Barryvdh\DomPDF\Facade\Pdf::loadView('adviser.report-export',$data+['sessions'=>$sessions,'startDate'=>$filters['start']->copy()->timezone('Asia/Manila'),'endDate'=>$filters['end']->copy()->timezone('Asia/Manila')])->download('compass-adviser-report.pdf');
        return response()->streamDownload(function() use($sessions,$data) {
            $file=fopen('php://output','w');
            foreach($data['metrics'] as $key=>$value) fputcsv($file,[$key,$value ?? 'No data']);
            fputcsv($file,[]); fputcsv($file,['Session','Helper alias','Concern','Status','Activity date (Asia/Manila)']);
            foreach($sessions as $session) {
                $concern=$session->concern?->concern_name ?? '';
                if(preg_match('/^[=+@\-]/',$concern)) $concern="'".$concern;
                fputcsv($file,[$session->reference_number,$session->helper?->public_alias ?? 'Unassigned',$concern,$session->session_status,($session->end_time ?? $session->start_time ?? $session->submitted_at ?? $session->created_date ?? $session->created_at)->copy()->timezone('Asia/Manila')->format('Y-m-d H:i:s')]);
            }
            fclose($file);
        },'compass-adviser-report.csv',['Content-Type'=>'text/csv','Cache-Control'=>'private, no-store']);
    }
}
