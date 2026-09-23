<?php
namespace App\Http\Controllers;
use App\Services\{ScreeningInstrument, SeekerWorkflowService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
class RiskClassificationController extends Controller {
    public function classify(Request $request) {
        Gate::authorize('seeker-workflow');
        abort_if($request->filled('seeker_id') && (int)$request->seeker_id !== (int)$request->user()->helpSeeker->id,403);
        abort_if($request->filled('session_id'),409,'Use the existing request workflow.');
        $rules=[]; foreach(ScreeningInstrument::rules() as $key=>$value) $rules['screening_responses.'.$key]=$value;
        $data=$request->validate($rules);
        app(SeekerWorkflowService::class)->screen($request->user(),$data['screening_responses']);
        return response()->json(['success'=>true,'redirect'=>route('request.matching')]);
    }
    public function updateRiskClassification(Request $request) {
        abort_unless($request->user()->role==='adviser',403);
        $data=$request->validate(['session_id'=>'required|integer']);
        return app(ScreeningReviewController::class)->review($request,\App\Models\Session::findOrFail($data['session_id']));
    }
}
