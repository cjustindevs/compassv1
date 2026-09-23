<?php
namespace App\Http\Controllers\Adviser;
use App\Http\Controllers\Controller;
use App\Models\{TrainingRecommendation,HelperCompetencyHistory};
use App\Services\{AdviserScope,TrainingRecommendationService};
use Illuminate\Http\Request;
class AdviserTrainingController extends Controller {
    public function index(Request $request) {
        $user=$request->user(); abort_unless($user?->is_active && in_array($user->role,['adviser','helper']),403);
        $isAdviser=$user->role==='adviser';
        $adviserId=$isAdviser ? app(AdviserScope::class)->actor()->id : null;
        if(!$isAdviser) abort_unless($user->helper,403);
        $scope=fn($q)=>$isAdviser ? $q->where('adviser_id',$adviserId) : $q->whereKey($user->helper->id);
        $tasks=TrainingRecommendation::with(['helper','evaluation'])->whereHas('helper',$scope)->latest('id')->paginate(15)->withQueryString();
        $evaluations=$isAdviser ? HelperCompetencyHistory::with('helper')->whereHas('helper',$scope)->whereNotNull('rubric_version')->latest('id')->get() : collect();
        return view('adviser.training',compact('tasks','evaluations','isAdviser'));
    }
    public function store(Request $request) {
        app(AdviserScope::class)->actor();
        $data=$request->validate(['evaluation_id'=>'required|integer|exists:helper_competency_history,id','criterion'=>'required|string','reason'=>'required|string|min:10|max:2000','activity'=>'required|string|max:2000','priority'=>'required|in:normal,high','due_date'=>'nullable|date|after_or_equal:today']);
        app(TrainingRecommendationService::class)->create($data);
        return back()->with('success','Training recommendation assigned.');
    }
    public function update(Request $request, TrainingRecommendation $training) {
        $data=$request->validate(['status'=>'required|in:in_progress,completed,reviewed,cancelled','completion_evidence'=>'nullable|string|max:3000','review_notes'=>'nullable|string|max:2000']);
        app(TrainingRecommendationService::class)->transition($training,$data);
        return back()->with('success','Training status recorded.');
    }
}
