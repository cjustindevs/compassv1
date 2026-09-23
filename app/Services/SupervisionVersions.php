<?php
namespace App\Services;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
class SupervisionVersions {
    public function record(Model $record, string $reason): void {
        abort_unless(trim($reason) !== '', 422, 'A reason is required.');
        // Call within the transaction holding the parent record lock.
        $version = (int) DB::table('supervision_record_versions')->where('record_type',$record->getTable())->where('record_id',$record->id)->max('version') + 1;
        DB::table('supervision_record_versions')->insert(['record_type'=>$record->getTable(),'record_id'=>$record->id,'version'=>$version,'actor_id'=>auth()->id(),'reason'=>$reason,'snapshot'=>json_encode($record->getAttributes(), JSON_THROW_ON_ERROR),'created_at'=>now()]);
    }
    public function history(Model $record) {
        return DB::table('supervision_record_versions')->where('record_type',$record->getTable())->where('record_id',$record->id)->orderByDesc('version')->get();
    }
}
