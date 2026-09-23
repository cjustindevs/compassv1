<?php
namespace App\Services;
class CompetencyRubric {
    public const VERSION = 'compass-v4-session-251-252';
    public const CRITERIA = [
        'active_listening'=>['Active Listening',25,'active_listening_score'],
        'empathy'=>['Empathy',25,'empathy_score'],
        'respect_professionalism'=>['Respect and Professionalism',20,'respect_score'],
        'ethical_practices'=>['Ethical Practices',20,'ethical_practices_score'],
        'referral_accuracy'=>['Referral Judgment',10,'referral_accuracy_score'],
    ];
    public static function score(array $data): float {
        if (array_sum(array_column(self::CRITERIA,1)) !== 100) throw new \LogicException('Rubric weights must total 100.');
        $score=0;
        foreach(self::CRITERIA as $key=>$criterion) {
            abort_unless(isset($data[$key]) && filter_var($data[$key],FILTER_VALIDATE_INT)!==false && $data[$key]>=1 && $data[$key]<=5,422,'Rate every criterion independently from 1 to 5.');
            $score += $data[$key]*$criterion[1]/100;
        }
        return round($score,2);
    }
    public static function level(float $score): string { return match(true) { $score>=4.5=>'Outstanding',$score>=3.5=>'Very Good',$score>=2.5=>'Satisfactory',$score>=1.5=>'Needs Improvement',default=>'Unsatisfactory' }; }
}
