<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TrainingRecommendation extends Model {
    protected $guarded=['id'];
    protected $casts=['due_date'=>'date','acknowledged_at'=>'datetime','completed_at'=>'datetime','reviewed_at'=>'datetime'];
    public function helper() { return $this->belongsTo(Helper::class); }
    public function adviser() { return $this->belongsTo(Adviser::class); }
    public function evaluation() { return $this->belongsTo(HelperCompetencyHistory::class,'evaluation_id'); }
}
