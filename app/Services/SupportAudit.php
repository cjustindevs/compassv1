<?php
namespace App\Services;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
class SupportAudit {
    public static function record(string $action, Model $target, array $metadata = [], string $outcome = 'success'): void {
        AuditLog::create(['user_account_id'=>auth()->id(), 'action'=>$action, 'module'=>'seeker_workflow',
            'description'=>$action, 'target_type'=>$target->getTable(), 'target_id'=>$target->getKey(),
            'outcome'=>$outcome, 'metadata'=>$metadata, 'ip_address'=>request()->ip(), 'user_agent'=>substr((string) request()->userAgent(),0,1000)]);
    }
}
