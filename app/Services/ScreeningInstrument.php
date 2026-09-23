<?php
namespace App\Services;
class ScreeningInstrument {
    public const VERSION = 'compass-v4-proposed-1';
    public const QUESTIONS = [
        'suicidal_thoughts'=>'We want to support you better. Do you think of harming yourself or ending your life?',
        'immediate_intent'=>'Do you currently intend to harm yourself or another person?',
        'current_suicide_plan'=>'Do you have a specific plan to harm yourself?',
        'access_to_means'=>'Do you have access to the means to carry out that plan?',
        'ongoing_self_harm'=>'Are you currently harming yourself or making an attempt to end your life?',
        'recent_attempt_needs_assistance'=>'Have you recently made an attempt that needs immediate assistance?',
        'immediate_threat_to_life'=>'Is there another immediate threat to your life or safety?',
        'immediate_threat_to_others'=>'Is there an immediate threat to another person?',
        'recent_self_harm'=>'Have you recently harmed yourself, even if it is not happening now?',
        'severe_distress'=>'Are you experiencing severe emotional distress?',
        'suspected_abuse'=>'Are you concerned about abuse or violence affecting you?',
        'referral_indicator'=>'Has a professional or adviser recommended urgent professional support for your current concern?',
        'recurring_distress'=>'Has your emotional distress been recurring or persistent?',
        'sleep_affected'=>'Is your sleep affected?',
        'concentration_affected'=>'Is your concentration affected?',
        'attendance_affected'=>'Is your attendance affected?',
        'schoolwork_affected'=>'Is your schoolwork or usual daily activity affected?',
        'relationships_affected'=>'Are your relationships affected?',
        'difficulty_coping'=>'Are you having difficulty coping?',
        'emotionally_overwhelmed'=>'Do you feel emotionally overwhelmed?',
    ];
    public static function approved(): bool {
        return filled(config('screening.approval_reference')) && config('screening.approved_version') === self::VERSION;
    }
    public static function rules(): array { return array_fill_keys(array_keys(self::QUESTIONS), 'required|in:yes,no,prefer_not_to_say'); }
}
