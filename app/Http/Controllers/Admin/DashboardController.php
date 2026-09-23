<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{User,Session,AuditLog};
use Illuminate\Http\Request;
use Illuminate\View\View;
class DashboardController extends Controller {
    public function __invoke(Request $request): View {
        $total=User::count();
        $primaryStats=[
            ['label'=>'Total Users','value'=>number_format($total),'detail'=>'registered accounts','icon'=>'users','tone'=>'green'],
            ['label'=>'Active Accounts','value'=>number_format(User::where('is_active',true)->count()),'detail'=>'enabled accounts; not online presence','icon'=>'user-check','tone'=>'green'],
            ['label'=>'Active Sessions','value'=>number_format(Session::where('session_status','active')->count()),'detail'=>'peer-support sessions','icon'=>'activity','tone'=>'blue'],
            ['label'=>'Unverified Accounts','value'=>number_format(User::whereNull('email_verified_at')->count()),'detail'=>'email verification outstanding','icon'=>'shield','tone'=>'orange'],
        ];
        $labels=[]; $values=[];
        foreach(range(5,0) as $offset) {
            $month=now('Asia/Manila')->startOfMonth()->subMonths($offset);
            $labels[]=$month->format('M Y');
            $values[]=User::where('created_at','<=',$month->copy()->endOfMonth()->utc())->count();
        }
        $max=max(4,(int)(ceil(max($values)/4)*4));
        $charts=['userGrowth'=>['labels'=>$labels,'max'=>$max,'ticks'=>[$max,$max*.75,$max*.5,$max*.25,0],'series'=>[['name'=>'Users','color'=>'#20bd68','values'=>$values,'fill'=>true]]]];
        $logs=AuditLog::whereIn('module',['auth','authentication','users','system','backups'])->latest('id')->limit(6)->get();
        $activities=$logs->map(fn($log)=>['message'=>\Illuminate\Support\Str::headline($log->action),'time'=>$log->created_at->diffForHumans(),'icon'=>'activity','tone'=>'green'])->all();
        $recentLogs=$logs->map(fn($log)=>['title'=>\Illuminate\Support\Str::headline($log->action),'actor'=>'Account #'.($log->user_account_id ?? 'system'),'area'=>$log->module,'datetime'=>$log->created_at->timezone('Asia/Manila')->format('Y-m-d H:i:s'),'ip'=>$log->ip_address ?? 'Not recorded','status'=>$log->outcome ?? 'Recorded','tone'=>'blue'])->all();
        return view('admin.dashboard',['admin'=>$request->user(),'searchQuery'=>$request->string('q')->trim()->toString(),'primaryStats'=>$primaryStats,'systemStatuses'=>[],'charts'=>$charts,'activities'=>$activities,'recentLogs'=>$recentLogs]);
    }
}
