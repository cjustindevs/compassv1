<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\HelperSchedule;
use App\Models\Session;
use App\Services\AdviserArchive;
use App\Services\AdviserScope;
use App\Services\SupportAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdviserArchiveController extends Controller
{
    public function store(Request $request, AdviserArchive $archive)
    {
        app(AdviserScope::class)->actor();
        $data = $request->validate(['record_type'=>'required|in:session,duty', 'record_id'=>'required|integer|min:1', 'restore'=>'nullable|boolean']);
        DB::transaction(function () use ($data, $request, $archive) {
            $model = $data['record_type'] === 'session' ? Session::class : HelperSchedule::class;
            $record = $model::whereKey($data['record_id'])->lockForUpdate()->firstOrFail();
            $archive->authorize($record);
            $restore = $request->boolean('restore');
            if (! $restore && ! $archive->canArchive($record)) {
                throw ValidationException::withMessages(['archive'=>'Only past duty days and concluded, reviewed cases without ongoing emergency or referral work can be archived.']);
            }
            if (($record->archived_at !== null) === ! $restore) return;
            $record->forceFill(['archived_at'=>$restore ? null : now(), 'archived_by'=>$restore ? null : $request->user()->id])->save();
            SupportAudit::record($restore ? 'record_restored' : 'record_archived', $record, ['purpose'=>'adviser_history']);
        });
        return back()->with('success', $request->boolean('restore') ? 'Record restored. Its original status is unchanged.' : 'Record archived. Its history has been preserved.');
    }
}
