<?php
namespace App\Services;
class EvaluationInstrument {
    // Store categorical answers as authoritative. Legacy numeric columns use a documented
    // evenly spaced 1-10 reporting scale; these are experience ratings, never clinical scores.
    public const OPTIONS = [
        'helpfulness_score'=>['Very Helpful','Helpful','Neutral','Not Helpful'],
        'comfort_score'=>['Very Comfortable','Comfortable','Slightly Comfortable','Not Comfortable'],
        'feeling_after_score'=>['Better','Slightly Better','The Same','Worse'],
        'understood_score'=>['Yes','Somewhat','No'],
        'reuse_score'=>['Yes','Maybe','No'],
    ];
    public const LABELS = ['helpfulness_score'=>'How helpful was the listener during your session?', 'comfort_score'=>'How comfortable did you feel during the conversation?', 'feeling_after_score'=>'How do you feel after the session?', 'understood_score'=>'Did you feel that the listener understood you?', 'reuse_score'=>'Would you use this service again?'];
    public static function rules(): array {
        $rules=[]; foreach(self::OPTIONS as $key=>$values) $rules[$key]=['required',\Illuminate\Validation\Rule::in($values)]; return $rules;
    }
    public static function scores(array $answers): array {
        $scores=[]; foreach(self::OPTIONS as $key=>$values) $scores[$key]=(int)round(10 - 9 * array_search($answers[$key],$values,true)/(count($values)-1)); return $scores;
    }
}
