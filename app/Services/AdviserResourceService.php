<?php
namespace App\Services;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
class AdviserResourceService {
    public function save(Model $resource, array $values, string $reason): Model {
        app(AdviserScope::class)->actor();
        return DB::transaction(function() use($resource,$values,$reason) {
            if($resource->exists) $resource=$resource->newQuery()->lockForUpdate()->findOrFail($resource->id);
            $versions=app(SupervisionVersions::class);
            if($resource->exists && $versions->history($resource)->isEmpty()) $versions->record($resource,'Original published or draft record');
            $resource->forceFill($values+['managed_by'=>auth()->id()])->save();
            $versions->record($resource,$reason);
            SupportAudit::record($resource->archived_at ? 'resource_archived' : 'resource_saved',$resource,['purpose'=>'institutional_resource_management']);
            return $resource;
        },3);
    }
}
