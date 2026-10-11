<?php

namespace App\Services;

class CompactScreening
{
    public const VERSION = 'compass-compact-restored-1';
    public const FORM_VERSION = 'compass-compact-explicit-1';
    public const SINGLE_VERSION = 'compass-single-safety-1';
    public const SINGLE_QUESTION = 'We want to support you better. Have you recently had thoughts of harming yourself or ending your life?';
    public const FIELDS = ['current_suicide_plan', 'suicidal_thoughts', 'severe_distress', 'recurring_distress', 'difficulty_coping'];

    public static function rules(): array
    {
        return array_fill_keys(self::FIELDS, 'required|boolean');
    }

    public static function questions(): array
    {
        return array_intersect_key(ScreeningInstrument::QUESTIONS, array_flip(self::FIELDS));
    }

    public static function formRules(bool $partial = false): array
    {
        return array_fill_keys(self::FIELDS, ($partial ? 'sometimes' : 'required').'|in:yes,no,prefer_not_to_say');
    }

    public function classifyForm(array $answers): array
    {
        // An explicit current plan keeps the existing emergency precedence.
        // Unknown answers are never converted into negative evidence.
        if (($answers['current_suicide_plan'] ?? null) !== 'yes'
            && in_array('prefer_not_to_say', $answers, true)) {
            throw new \RuntimeException('Screening requires clarification.');
        }
        return $this->classify(array_map(fn ($answer) => $answer === 'yes', $answers));
    }

    public function classifySingle(array $answers): array
    {
        $answer = $answers['suicidal_thoughts'] ?? null;
        if (!in_array($answer, ['yes', 'no'], true)) {
            throw new \RuntimeException('The single safety answer requires Adviser clarification.');
        }
        // This restores the short intake, not a complete five-answer assessment.
        // Thoughts do not establish a current plan; unasked answers stay absent.
        $risk = $answer === 'yes' ? 'high' : 'low';
        return ['risk_level'=>$risk, 'priority'=>RiskClassificationService::PRIORITY_ORDER[$risk],
            'rule_code'=>'single_safety_'.$answer, 'action'=>$answer === 'yes' ? 'adviser_review_required' : 'normal_queuing',
            'reason'=>$answer === 'yes'
                ? 'Disclosed thoughts require Adviser review; a current plan was not assessed.'
                : 'Standard queue priority from limited single-question screening; other support questions were not assessed.'];
    }

    public function classify(array $answers): array
    {
        // Restore the previous five-answer routing. Do not manufacture answers
        // to questions that were not asked, or call this a clinical diagnosis.
        $yes = fn ($field) => (bool) $answers[$field];
        $risk = $yes('current_suicide_plan') ? 'emergency'
            : (($yes('suicidal_thoughts') || $yes('severe_distress')) ? 'high'
            : (($yes('recurring_distress') || $yes('difficulty_coping')) ? 'moderate' : 'low'));

        return ['risk_level'=>$risk, 'priority'=>RiskClassificationService::PRIORITY_ORDER[$risk],
            'rule_code'=>'compact_'.$risk, 'action'=>$risk === 'emergency' ? 'emergency_escalation' : ($risk === 'high' ? 'adviser_review_required' : 'normal_queuing'),
            'reason'=>'Preliminary routing from the five submitted support answers.'];
    }
}
