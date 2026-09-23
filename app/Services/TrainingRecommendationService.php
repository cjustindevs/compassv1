<?php
namespace App\Services;
use App\Models\{TrainingRecommendation,HelperCompetencyHistory,Notification};
use Illuminate\Support\Facades\DB;
class TrainingRecommendationService {
    public function create(array $data): TrainingRecommendation {
        $adviser=app(AdviserScope::class)->actor();
        return DB::transaction(function() use($data,$adviser) {
            $evaluation=HelperCompetencyHistory::lockForUpdate()->findOrFail($data['evaluation_id']);
            abort_unless($evaluation->helper?->adviser_id===$adviser->id,403);
            abort_unless(isset(CompetencyRubric::CRITERIA[$data['criterion']]) && $evaluation->rubric_version && $evaluation->evidence,422,'Use a documented, versioned competency evaluation.');
            $task=TrainingRecommendation::create(array_intersect_key($data,array_flip(['criterion','reason','activity','priority','due_date']))+['evaluation_id'=>$evaluation->id,'helper_id'=>$evaluation->helper_id,'adviser_id'=>$adviser->id]);
            app(SupervisionVersions::class)->record($task,'Training assigned on documented evidence');
            SupportAudit::record('training_assigned',$task);
            $this->notify($task->helper->user_account_id,'Training recommendation assigned','/helper/training');
            return $task;
        });
    }
    public function transition(TrainingRecommendation $task, array $data): void {
        DB::transaction(function() use($task,$data) {
            $task=TrainingRecommendation::lockForUpdate()->findOrFail($task->id);
            $actor=auth()->user(); abort_unless($actor?->is_active,403);
            $isHelper=$actor->role==='helper' && $actor->helper?->id===$task->helper_id;
            $isAdviser=$actor->role==='adviser' && $actor->adviser && $task->helper->adviser_id===$actor->adviser->id;
            abort_unless($isHelper || $isAdviser,403);
            $target=$data['status'];
            $allowed=$isHelper ? ['assigned'=>['in_progress'],'in_progress'=>['completed']] : ['assigned'=>['cancelled'],'in_progress'=>['cancelled'],'completed'=>['reviewed','in_progress','cancelled']];
            abort_unless(in_array($target,$allowed[$task->status] ?? [],true),409,'This transition is not available.');
            if($target==='completed') abort_unless(trim($data['completion_evidence'] ?? '')!=='',422,'Completion evidence is required.');
            if($isAdviser) abort_unless(trim($data['review_notes'] ?? '')!=='',422,'Review notes are required.');
            $task->status=$target;
            if($isHelper) $task->acknowledged_at ??= now();
            if($target==='completed') { $task->completed_at=now(); $task->completion_evidence=$data['completion_evidence']; }
            if($target==='reviewed') $task->reviewed_at=now();
            if($isAdviser) { $task->adviser_id=$actor->adviser->id; $task->review_notes=$data['review_notes']; }
            $task->save(); app(SupervisionVersions::class)->record($task,'Training status: '.$target);
            SupportAudit::record('training_'.$target,$task);
            $this->notify($isHelper ? $task->helper->adviser?->user_account_id : $task->helper->user_account_id,'Training recommendation updated',$isHelper ? '/adviser/training' : '/helper/training');
        },3);
    }
    private function notify(?int $id,string $title,string $link): void { if (!$id) return; Notification::create(['user_account_id'=>$id,'title'=>$title,'message'=>'A training recommendation requires your attention.','notification_type'=>'system','link'=>$link]); }
}
