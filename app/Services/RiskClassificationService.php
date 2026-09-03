<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

class RiskClassificationService
{
    public const RISK_EMERGENCY = 'emergency';
    public const RISK_HIGH = 'high';
    public const RISK_MODERATE = 'moderate';
    public const RISK_LOW = 'low';

    public const PRIORITY_ORDER = [
        self::RISK_EMERGENCY => 1,
        self::RISK_HIGH => 2,
        self::RISK_MODERATE => 3,
        self::RISK_LOW => 4,
    ];

    public function classifyRisk(array $screeningResponses): array
    {
        $this->validateResponses($screeningResponses);
        $this->checkContradictions($screeningResponses);

        if ($this->isEmergencyRisk($screeningResponses)) {
            return $this->result(self::RISK_EMERGENCY, true, true, 'emergency_escalation', 'Immediate safety threat detected');
        }

        if ($this->isHighRisk($screeningResponses)) {
            return $this->result(self::RISK_HIGH, true, false, 'adviser_review_required', 'Serious safety or referral indicators');
        }

        if ($this->isModerateRisk($screeningResponses)) {
            return $this->result(self::RISK_MODERATE, false, false, 'elevated_priority', 'Persistent emotional distress affecting daily functioning');
        }

        return $this->result(self::RISK_LOW, false, false, 'normal_queuing', 'Temporary stress or emotional discomfort');
    }

    public function getRequiredCompetencyLevel(string $riskLevel): int
    {
        return match ($riskLevel) {
            self::RISK_EMERGENCY => 4,
            self::RISK_HIGH => 3,
            self::RISK_MODERATE => 2,
            default => 1,
        };
    }

    public function getQueuePriority(string $riskLevel): int
    {
        return self::PRIORITY_ORDER[$riskLevel] ?? self::PRIORITY_ORDER[self::RISK_LOW];
    }

    private function isEmergencyRisk(array $responses): bool
    {
        return $this->anyYes($responses, [
            'current_suicide_plan',
            'access_to_means',
            'ongoing_self_harm',
            'recent_attempt_needs_assistance',
            'immediate_threat_to_life',
            'immediate_threat_to_others',
        ]);
    }

    private function isHighRisk(array $responses): bool
    {
        return ! $this->isEmergencyRisk($responses) && $this->anyYes($responses, [
            'suicidal_thoughts',
            'recent_self_harm',
            'severe_distress',
            'suspected_abuse',
            'suspected_violence',
            'referral_indicator',
        ]);
    }

    private function isModerateRisk(array $responses): bool
    {
        return ! $this->isEmergencyRisk($responses)
            && ! $this->isHighRisk($responses)
            && $this->anyYes($responses, [
                'recurring_distress',
                'sleep_affected',
                'concentration_affected',
                'attendance_affected',
                'schoolwork_affected',
                'relationships_affected',
                'difficulty_coping',
                'emotionally_overwhelmed',
            ]);
    }

    private function validateResponses(array $responses): void
    {
        $required = ['current_suicide_plan', 'suicidal_thoughts', 'severe_distress', 'recurring_distress', 'difficulty_coping'];
        $missing = array_values(array_filter($required, fn (string $field) => ! array_key_exists($field, $responses)));

        if ($missing !== []) {
            throw new InvalidArgumentException('Missing required screening responses: ' . implode(', ', $missing));
        }
    }

    private function checkContradictions(array $responses): void
    {
        if ($this->isYes($responses['current_suicide_plan'] ?? false) && ! $this->isYes($responses['suicidal_thoughts'] ?? false)) {
            throw new RuntimeException('Contradictory responses detected. Please clarify.');
        }
    }

    private function anyYes(array $responses, array $fields): bool
    {
        foreach ($fields as $field) {
            if ($this->isYes($responses[$field] ?? false)) {
                return true;
            }
        }

        return false;
    }

    private function isYes(mixed $value): bool
    {
        return $value === true || $value === 1 || in_array(strtolower((string) $value), ['yes', 'true', '1'], true);
    }

    private function result(string $riskLevel, bool $immediate, bool $bypassQueue, string $action, string $reason): array
    {
        return [
            'risk_level' => $riskLevel,
            'priority' => $this->getQueuePriority($riskLevel),
            'requires_immediate_action' => $immediate,
            'bypass_queue' => $bypassQueue,
            'action' => $action,
            'reason' => $reason,
        ];
    }
}
