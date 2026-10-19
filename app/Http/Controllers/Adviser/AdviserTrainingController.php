<?php
namespace App\Http\Controllers\Adviser;
use App\Http\Controllers\Controller;
use App\Models\{TrainingRecommendation,Helper};
use App\Services\{AdviserScope,TrainingRecommendationService,AdviserCompetencyComparison,CompetencyRubric};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class AdviserTrainingController extends Controller {
    public function index(Request $request) {
        $user=$request->user(); abort_unless($user?->is_active && in_array($user->role,['adviser','helper']),403);
        $isAdviser=$user->role==='adviser';
        $adviserId=$isAdviser ? app(AdviserScope::class)->actor()->id : null;
        if(!$isAdviser) abort_unless($user->helper,403);
        $scope=fn($q)=>$isAdviser ? $q->where('adviser_id',$adviserId) : $q->whereKey($user->helper->id);
        $selectedEvaluation = null;
        $selectedHelper = null;
        $selectedCriterion = array_key_first(CompetencyRubric::CRITERIA);
        $evaluations = collect();
        if ($isAdviser) {
            $selection = $request->validate(['helper_id'=>'nullable|integer', 'evaluation_id'=>'nullable|integer',
                'criterion'=>['nullable', Rule::in(array_keys(CompetencyRubric::CRITERIA))]]);
            if (isset($selection['helper_id'])) $selectedHelper = Helper::where('adviser_id', $adviserId)->findOrFail($selection['helper_id']);
            $evaluations = app(AdviserCompetencyComparison::class)->evaluations($adviserId)
                ->when($selectedHelper, fn ($rows) => $rows->where('helper_id', $selectedHelper->id))->values();
            if (isset($selection['evaluation_id'])) {
                $selectedEvaluation = $evaluations->firstWhere('id', $selection['evaluation_id']);
                abort_unless($selectedEvaluation, 404);
            }
            $selectedCriterion = $selection['criterion'] ?? $selectedCriterion;
        }
        $tasks=TrainingRecommendation::with(['helper','evaluation'])->whereHas('helper',$scope)
            ->when($selectedHelper, fn ($q) => $q->where('helper_id', $selectedHelper->id))
            ->latest('id')->paginate(15)->withQueryString();
        return view('adviser.training',compact('tasks','evaluations','isAdviser','selectedEvaluation','selectedCriterion','selectedHelper'));
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
